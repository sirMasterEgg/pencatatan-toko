<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\Transaction;
use App\Support\Parse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private ProductService $products,
        private CustomerService $customers,
        private CustomerPriceService $customerPrices,
    ) {}

    public static function calculateSubtotal(float|int|string $quantity, int $unitPrice, int $discount = 0): int
    {
        return (int) round((float) $quantity * $unitPrice) - $discount;
    }

    public function create(array $data, TransactionStatus $status = TransactionStatus::Confirmed): Transaction
    {
        $this->assertSavableStatus($status);
        $data = $this->normalize($data);
        $this->validate($data);

        return DB::transaction(function () use ($data, $status) {
            $transactedAt = Carbon::parse($data['transacted_at']);

            $transaction = Transaction::create([
                'number' => $this->generateNumber($transactedAt),
                'transacted_at' => $transactedAt,
                'customer_id' => $this->resolveCustomerId($data['customer'] ?? null),
                'channel' => $data['channel'],
                'input_source' => 'form',
                'status' => $status,
                'payment_status' => $data['payment_status'],
                'total' => 0,
                'note' => $data['note'] ?? null,
            ]);

            $transaction->update(['total' => $this->createItems($transaction, $data['items'])]);

            return $transaction->load('items.product', 'customer');
        });
    }

    public function update(Transaction $transaction, array $data, TransactionStatus $status): Transaction
    {
        if (! $transaction->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.']);
        }

        $this->assertSavableStatus($status);

        if ($transaction->status === TransactionStatus::Confirmed && $status === TransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Transaksi yang sudah dikonfirmasi tidak bisa dijadikan draf.']);
        }

        $data = $this->normalize($data);
        $this->validate($data);

        return DB::transaction(function () use ($transaction, $data, $status) {
            // Barang yang lahir dari transaksi ini tetap ditandai "Barang baru" (list_price null).
            $newProductIds = $transaction->items()->whereNull('list_price')->pluck('product_id')->all();

            $transaction->items()->delete();

            $transaction->update([
                'transacted_at' => Carbon::parse($data['transacted_at']),
                'customer_id' => $this->resolveCustomerId($data['customer'] ?? null),
                'channel' => $data['channel'],
                'status' => $status,
                'payment_status' => $data['payment_status'],
                'note' => $data['note'] ?? null,
            ]);

            $total = $this->createItems($transaction, $data['items']);

            $transaction->items()->whereIn('product_id', $newProductIds)->update(['list_price' => null]);
            $transaction->update(['total' => $total]);

            return $transaction->load('items.product', 'customer');
        });
    }

    public function deleteDraft(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Hanya draf yang bisa dihapus.']);
        }

        DB::transaction(function () use ($transaction) {
            $transaction->items()->delete();
            $transaction->delete();
        });
    }

    /**
     * Membuat semua item untuk transaksi dan mengembalikan totalnya.
     */
    private function createItems(Transaction $transaction, array $items): int
    {
        $total = 0;

        foreach ($items as $index => $item) {
            $unitPrice = (int) $item['unit_price'];
            $discount = (int) ($item['discount'] ?? 0);
            $priceMode = $item['price_mode'] ?? 'this_only';

            if (! empty($item['product_id'])) {
                $product = Product::with('currentPrice')->findOrFail($item['product_id']);
                $this->assertSelectable($product, $index);
                $customerPrice = $this->customerPriceFor($transaction, $product);
                $listPrice = $this->applyExistingProduct($product, $unitPrice, $priceMode, $transaction, $customerPrice);
            } else {
                $new = $item['new_product'];
                $product = $this->products->findByNameAndUnit($new['name'], $new['unit']);

                if ($product) {
                    $product->load('currentPrice');
                    $customerPrice = $this->customerPriceFor($transaction, $product);
                    $listPrice = $this->applyExistingProduct($product, $unitPrice, 'this_only', $transaction, $customerPrice);
                } else {
                    $product = $this->products->create(
                        name: $new['name'],
                        unit: $new['unit'],
                        sellPrice: $unitPrice,
                        costPrice: isset($new['cost_price']) && $new['cost_price'] !== '' ? (int) $new['cost_price'] : null,
                        status: ProductStatus::NeedsReview,
                        sourceTransactionId: $transaction->id,
                    );
                    $listPrice = null;
                    $customerPrice = null;
                }
            }

            $subtotal = self::calculateSubtotal($item['quantity'], $unitPrice, $discount);

            if ($subtotal < 0) {
                throw ValidationException::withMessages(["items.{$index}.discount" => 'Diskon melebihi nilai baris.']);
            }

            $transaction->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit' => $product->unit,
                'unit_price' => $unitPrice,
                'list_price' => $listPrice,
                'customer_price' => $customerPrice,
                'discount' => $discount,
                'subtotal' => $subtotal,
            ]);

            $total += $subtotal;
        }

        return $total;
    }

    private function assertSavableStatus(TransactionStatus $status): void
    {
        if (! in_array($status, [TransactionStatus::Draft, TransactionStatus::Confirmed], true)) {
            throw ValidationException::withMessages(['status' => 'Status simpan harus draf atau dikonfirmasi.']);
        }
    }

    public function cancel(Transaction $transaction, ?string $reason = null): Transaction
    {
        if ($transaction->status !== TransactionStatus::Confirmed) {
            throw ValidationException::withMessages(['status' => 'Hanya transaksi yang dikonfirmasi yang bisa dibatalkan.']);
        }

        $transaction->update([
            'status' => TransactionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $transaction;
    }

    public function updatePaymentStatus(Transaction $transaction, PaymentStatus $status): Transaction
    {
        if ($transaction->status !== TransactionStatus::Confirmed) {
            throw ValidationException::withMessages(['payment_status' => 'Status bayar hanya bisa diubah pada transaksi yang dikonfirmasi.']);
        }

        $transaction->update(['payment_status' => $status]);

        return $transaction;
    }

    /**
     * Returns the list price in effect just before this transaction (null if none).
     */
    private function applyExistingProduct(Product $product, int $unitPrice, string $priceMode, Transaction $transaction, ?int $customerPrice = null): ?int
    {
        $current = $product->currentPrice;
        $listPrice = $current?->sell_price;

        if ($listPrice === null) {
            $this->products->addPrice($product, $unitPrice, null, null, $transaction->id);
        } elseif ($priceMode === 'new_price' && $unitPrice !== $listPrice) {
            $this->products->addPrice($product, $unitPrice, $current->cost_price, null, $transaction->id);
        } elseif ($priceMode === 'customer_price' && $transaction->customer_id !== null && $unitPrice !== $customerPrice) {
            $this->customerPrices->set($transaction->customer_id, $product->id, $unitPrice, null, $transaction->id);
        }

        return $listPrice;
    }

    /**
     * Harga pelanggan yang berlaku untuk barang ini sebelum transaksi disimpan, atau null.
     */
    private function customerPriceFor(Transaction $transaction, Product $product): ?int
    {
        if ($transaction->customer_id === null) {
            return null;
        }

        return $this->customerPrices->priceAt($transaction->customer_id, $product->id);
    }

    private function assertSelectable(Product $product, int|string $index): void
    {
        if (! in_array($product->status, [ProductStatus::Active, ProductStatus::NeedsReview], true)) {
            throw ValidationException::withMessages(["items.{$index}.product_id" => 'Barang ini sudah tidak aktif.']);
        }
    }

    private function resolveCustomerId(?array $customer): ?int
    {
        if (empty($customer)) {
            return null;
        }

        if (! empty($customer['id'])) {
            return (int) $customer['id'];
        }

        return $this->customers->findOrCreate(
            $customer['name'],
            $customer['phone'] ?? null,
            $customer['address'] ?? null,
        )->id;
    }

    private function generateNumber(Carbon $transactedAt): string
    {
        $prefix = 'TRX-'.$transactedAt->format('Ymd').'-';

        $last = Transaction::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');

        $sequence = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    private function normalize(array $data): array
    {
        foreach ($data['items'] ?? [] as $index => $item) {
            if (is_array($item) && array_key_exists('quantity', $item)) {
                $data['items'][$index]['quantity'] = Parse::quantity($item['quantity']);
            }
        }

        return $data;
    }

    private function validate(array $data): void
    {
        $rules = [
            'transacted_at' => ['required', 'date', 'before_or_equal:now'],
            'channel' => ['required', Rule::enum(Channel::class)],
            'payment_status' => ['required', Rule::enum(PaymentStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'customer' => ['nullable', 'array'],
            'customer.id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer.name' => [
                Rule::requiredIf(fn () => is_array($data['customer'] ?? null) && empty($data['customer']['id'])),
                'nullable', 'string', 'max:255',
            ],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.new_product' => ['nullable', 'array'],
            'items.*.new_product.name' => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.new_product.unit' => ['required_without:items.*.product_id', 'nullable', Rule::in(config('shop.units'))],
            'items.*.new_product.cost_price' => ['nullable', 'integer', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.discount' => ['nullable', 'integer', 'min:0'],
            'items.*.price_mode' => ['nullable', Rule::in(['this_only', 'new_price', 'customer_price'])],
        ];

        $messages = [
            'items.required' => 'Transaksi harus punya minimal satu barang.',
            'items.min' => 'Transaksi harus punya minimal satu barang.',
            'transacted_at.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
            'items.*.quantity.decimal' => 'Jumlah paling banyak 3 angka di belakang koma.',
        ];

        $attributes = [
            'items.*.quantity' => 'jumlah',
            'items.*.unit_price' => 'harga satuan',
            'items.*.discount' => 'diskon',
            'items.*.new_product.name' => 'nama barang',
            'items.*.new_product.unit' => 'satuan',
            'customer.name' => 'nama pelanggan',
        ];

        Validator::make($data, $rules, $messages, $attributes)->validate();

        $errors = [];

        foreach ($data['items'] as $index => $item) {
            $unit = $item['new_product']['unit'] ?? null;

            if (! empty($item['product_id'])) {
                $product = Product::find($item['product_id']);
                $unit = $product?->unit;
                if ($product && ! in_array($product->status, [ProductStatus::Active, ProductStatus::NeedsReview], true)) {
                    $errors["items.{$index}.product_id"] = 'Barang ini sudah tidak aktif.';
                }
            }

            if (($item['price_mode'] ?? null) === 'customer_price' && empty($data['customer'])) {
                $errors["items.{$index}.price_mode"] = 'Pilih pelanggan dulu untuk menyimpan harga pelanggan.';
            }

            $quantity = (float) $item['quantity'];
            if ($unit !== null && ! in_array($unit, config('shop.decimal_units'), true) && floor($quantity) !== $quantity) {
                $errors["items.{$index}.quantity"] = "Jumlah untuk satuan {$unit} harus bilangan bulat.";
            }

            $subtotal = self::calculateSubtotal($item['quantity'], (int) $item['unit_price'], (int) ($item['discount'] ?? 0));
            if ($subtotal < 0) {
                $errors["items.{$index}.discount"] = 'Diskon melebihi nilai baris.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
