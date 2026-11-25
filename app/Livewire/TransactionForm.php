<?php

namespace App\Livewire;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\CustomerPriceService;
use App\Services\TransactionService;
use App\Support\Format;
use App\Support\Parse;
use App\Support\Phone;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TransactionForm extends Component
{
    #[Locked]
    public ?int $transactionId = null;

    #[Locked]
    public ?string $originalStatus = null;

    public string $transactedAt = '';

    /**
     * true: tanggal mengikuti waktu sekarang dan disimpan dengan waktu saat simpan.
     * false: tanggal diisi manual, atau tanggal asli transaksi yang sedang diubah.
     */
    public bool $autoTime = true;

    public string $channel = 'store';

    public string $paymentStatus = 'paid';

    public string $paidAmount = '';

    public string $note = '';

    public string $customerMode = 'none';

    public ?int $customerId = null;

    public string $customerLabel = '';

    public string $customerSearch = '';

    public array $customerResults = [];

    public string $customerName = '';

    public string $customerPhone = '';

    public array $items = [];

    public function mount(?Transaction $transaction = null): void
    {
        if ($transaction?->exists) {
            $this->fillFromTransaction($transaction);

            return;
        }

        $this->transactedAt = now()->format('Y-m-d\TH:i');
        $this->items = [$this->emptyRow()];
    }

    /**
     * Setiap permintaan Livewire, tanggal otomatis diperbarui ke waktu sekarang.
     */
    public function hydrate(): void
    {
        if ($this->autoTime) {
            $this->transactedAt = now()->format('Y-m-d\TH:i');
        }
    }

    /**
     * Dipanggil Livewire saat pengguna mengubah kolom tanggal sendiri.
     */
    public function updatedTransactedAt(): void
    {
        $this->autoTime = false;
    }

    public function useCurrentTime(): void
    {
        $this->autoTime = true;
        $this->transactedAt = now()->format('Y-m-d\TH:i');
    }

    private function fillFromTransaction(Transaction $transaction): void
    {
        $transaction->load('items.product.currentPrice', 'customer');

        $this->transactionId = $transaction->id;
        $this->originalStatus = $transaction->status->value;
        $this->autoTime = false;
        $this->transactedAt = $transaction->transacted_at->format('Y-m-d\TH:i');
        $this->channel = $transaction->channel->value;
        $this->paymentStatus = $transaction->payment_status->value;
        $this->paidAmount = $transaction->payment_status === PaymentStatus::Partial ? Format::thousands($transaction->paid_amount) : '';
        $this->note = $transaction->note ?? '';

        if ($transaction->customer) {
            $this->selectCustomer($transaction->customer->id);
        }

        $this->items = $transaction->items
            ->map(fn (TransactionItem $item) => array_merge($this->emptyRow(), [
                'mode' => 'existing',
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'unit' => $item->product->unit,
                'list_price' => $item->product->currentPrice?->sell_price,
                'customer_price' => $this->customerPriceFor($item->product_id),
                'quantity' => Format::quantityInput($item->quantity),
                'unit_price' => Format::thousands($item->unit_price),
                'discount' => Format::thousands($item->discount),
            ]))
            ->all();

        if (count($this->items) === 0) {
            $this->items = [$this->emptyRow()];
        }
    }

    private function emptyRow(): array
    {
        return [
            'key' => (string) Str::uuid(),
            'mode' => 'search',
            'search' => '',
            'results' => [],
            'product_id' => null,
            'name' => '',
            'unit' => '',
            'list_price' => null,
            'customer_price' => null,
            'cost_price' => null,
            'quantity' => '1',
            'unit_price' => null,
            'discount' => 0,
            'price_mode' => 'this_only',
        ];
    }

    public function addRow(): void
    {
        $this->items[] = $this->emptyRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);

        if (count($this->items) === 0) {
            $this->items = [$this->emptyRow()];
        }
    }

    public function updated($property, $value): void
    {
        if (preg_match('/^items\.(\d+)\.search$/', $property, $m)) {
            $index = (int) $m[1];
            $value = trim((string) $value);

            $this->items[$index]['results'] = mb_strlen($value) < 2 ? [] : $this->searchProducts($value);

            return;
        }

        if ($property === 'customerSearch') {
            $value = trim((string) $value);
            $this->customerResults = mb_strlen($value) < 2 ? [] : $this->searchCustomers($value);
        }
    }

    /**
     * Maksimal 20 barang yang namanya cocok, dikelompokkan per satuan
     * (urutan satuan mengikuti config('shop.units')), lalu urut nama di dalam kelompok.
     */
    private function searchProducts(string $term): array
    {
        $unitOrder = array_flip(config('shop.units'));

        return Product::selectable()
            ->with('currentPrice')
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->sortBy([
                fn (Product $a, Product $b) => ($unitOrder[$a->unit] ?? PHP_INT_MAX) <=> ($unitOrder[$b->unit] ?? PHP_INT_MAX),
                fn (Product $a, Product $b) => strcmp($a->name, $b->name),
            ])
            ->values()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'unit' => $p->unit,
                'price' => $p->currentPrice?->sell_price,
            ])
            ->all();
    }

    private function searchCustomers(string $term): array
    {
        $normalized = Phone::normalize($term);

        return Customer::query()
            ->where(function ($query) use ($term, $normalized) {
                $query->where('name', 'like', '%'.$term.'%');
                if ($normalized !== null) {
                    $query->orWhere('phone', 'like', '%'.$normalized.'%');
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Customer $c) => ['id' => $c->id, 'label' => $c->displayName(), 'phone' => $c->phone])
            ->all();
    }

    public function selectProduct(int $index, int $productId): void
    {
        $product = Product::selectable()->with('currentPrice')->find($productId);

        if (! $product || ! isset($this->items[$index])) {
            return;
        }

        $price = $product->currentPrice?->sell_price;
        $customerPrice = $this->customerPriceFor($product->id);

        $this->items[$index] = array_merge($this->items[$index], [
            'mode' => 'existing',
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'list_price' => $price,
            'customer_price' => $customerPrice,
            'unit_price' => Format::thousands($customerPrice ?? $price),
            'price_mode' => 'this_only',
            'search' => '',
            'results' => [],
        ]);
    }

    public function startNewProduct(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index] = array_merge($this->items[$index], [
            'mode' => 'new',
            'name' => trim($this->items[$index]['search']),
            'unit' => config('shop.units')[0],
            'product_id' => null,
            'list_price' => null,
            'unit_price' => null,
            'cost_price' => null,
        ]);
    }

    public function resetRow(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index] = $this->emptyRow();
        }
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return;
        }

        $this->customerMode = 'existing';
        $this->customerId = $customer->id;
        $this->customerLabel = $customer->phone ? $customer->displayName().' ('.$customer->phone.')' : $customer->displayName();
        $this->customerSearch = '';
        $this->customerResults = [];
        $this->refreshCustomerPrices();
    }

    public function startNewCustomer(): void
    {
        $this->customerMode = 'new';

        if (preg_match('/^[\d\s+\-]+$/', $this->customerSearch)) {
            $this->customerPhone = $this->customerSearch;
        } else {
            $this->customerName = $this->customerSearch;
        }

        $this->customerSearch = '';
        $this->customerResults = [];
        $this->refreshCustomerPrices();
    }

    public function clearCustomer(): void
    {
        $this->customerMode = 'none';
        $this->customerId = null;
        $this->customerLabel = '';
        $this->customerSearch = '';
        $this->customerResults = [];
        $this->customerName = '';
        $this->customerPhone = '';
        $this->refreshCustomerPrices();
    }

    /**
     * Harga pelanggan yang berlaku untuk pelanggan terpilih, atau null.
     */
    private function customerPriceFor(int $productId): ?int
    {
        if ($this->customerMode !== 'existing' || $this->customerId === null) {
            return null;
        }

        return app(CustomerPriceService::class)->priceAt($this->customerId, $productId);
    }

    /**
     * Dipanggil setiap kali pelanggan berganti. Baris yang harganya belum diubah
     * mengikuti harga acuan baru (harga pelanggan, atau harga umum).
     */
    private function refreshCustomerPrices(): void
    {
        foreach ($this->items as $index => $row) {
            if ($row['mode'] !== 'existing' || $row['product_id'] === null) {
                continue;
            }

            $oldReference = $row['customer_price'] ?? $row['list_price'];
            $customerPrice = $this->customerPriceFor($row['product_id']);

            if (Parse::rupiah($row['unit_price']) === $oldReference) {
                $this->items[$index]['unit_price'] = Format::thousands($customerPrice ?? $row['list_price']);
            }

            $this->items[$index]['customer_price'] = $customerPrice;

            if ($row['price_mode'] === 'customer_price' && $this->customerMode === 'none') {
                $this->items[$index]['price_mode'] = 'this_only';
            }
        }
    }

    public function rowSubtotal(array $row): int
    {
        $quantity = Parse::quantity($row['quantity'] ?? null);
        $unitPrice = Parse::rupiah($row['unit_price'] ?? null);

        if (! is_numeric($quantity) || ! is_int($unitPrice)) {
            return 0;
        }

        $discount = Parse::rupiah($row['discount'] ?? null);
        $discount = is_int($discount) ? $discount : 0;

        return TransactionService::calculateSubtotal($quantity, (int) $unitPrice, $discount);
    }

    /**
     * Sisa yang belum dibayar untuk status "Sebagian".
     */
    public function remaining(): int
    {
        $paid = Parse::rupiah($this->paidAmount);

        return max(0, $this->total() - (is_int($paid) ? $paid : 0));
    }

    public function total(): int
    {
        return array_sum(array_map(fn (array $row) => $this->rowSubtotal($row), $this->items));
    }

    public function save(TransactionService $service)
    {
        return $this->persist($service, TransactionStatus::Confirmed);
    }

    public function saveDraft(TransactionService $service)
    {
        return $this->persist($service, TransactionStatus::Draft);
    }

    public function saveAndNew(TransactionService $service)
    {
        return $this->persist($service, TransactionStatus::Confirmed, startNew: true);
    }

    private function persist(TransactionService $service, TransactionStatus $status, bool $startNew = false)
    {
        $this->resetErrorBag();

        $this->items = array_values(array_filter(
            $this->items,
            fn (array $row) => ! ($row['mode'] === 'search' && trim($row['search']) === ''),
        ));

        $pending = false;
        foreach ($this->items as $i => $row) {
            if ($row['mode'] === 'search') {
                $this->addError("items.$i.search", 'Pilih barang dari daftar atau buat sebagai barang baru.');
                $pending = true;
            }
        }
        if ($pending) {
            return;
        }

        if (count($this->items) === 0) {
            $this->items = [$this->emptyRow()];
            $this->addError('items', 'Pilih atau buat barang terlebih dahulu.');

            return;
        }

        $toInt = fn ($v) => Parse::rupiah($v);

        $items = [];
        foreach ($this->items as $row) {
            $item = [
                'quantity' => $row['quantity'],
                'unit_price' => $toInt($row['unit_price']),
                'discount' => $toInt($row['discount']) ?? 0,
            ];

            if ($row['mode'] === 'existing') {
                $item['product_id'] = $row['product_id'];
                $item['price_mode'] = $row['price_mode'];
            } else {
                $item['new_product'] = [
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'cost_price' => $toInt($row['cost_price']),
                ];
            }

            $items[] = $item;
        }

        $customer = match ($this->customerMode) {
            'existing' => ['id' => $this->customerId],
            'new' => ['name' => $this->customerName, 'phone' => $this->customerPhone],
            default => null,
        };

        $data = [
            'transacted_at' => $this->autoTime ? now()->format('Y-m-d H:i:s') : str_replace('T', ' ', $this->transactedAt),
            'channel' => $this->channel,
            'payment_status' => $this->paymentStatus,
            'paid_amount' => $this->paymentStatus === PaymentStatus::Partial->value ? Parse::rupiah($this->paidAmount) : null,
            'note' => $this->note !== '' ? $this->note : null,
            'customer' => $customer,
            'items' => $items,
        ];

        if ($this->transactionId !== null) {
            $transaction = $service->update(Transaction::findOrFail($this->transactionId), $data, $status);
        } else {
            $transaction = $service->create($data, $status);
        }

        $message = match (true) {
            $status === TransactionStatus::Draft => 'Draf '.$transaction->number.' berhasil disimpan.',
            $this->originalStatus === TransactionStatus::Confirmed->value => 'Transaksi '.$transaction->number.' berhasil diperbarui.',
            default => 'Transaksi '.$transaction->number.' berhasil disimpan.',
        };

        session()->flash('success', $message);

        if ($startNew) {
            session()->flash('last_transaction', ['id' => $transaction->id, 'number' => $transaction->number]);

            return $this->redirect(route('transactions.create'));
        }

        return $this->redirect(route('transactions.show', $transaction));
    }

    public function render()
    {
        return view('livewire.transaction-form', [
            'units' => config('shop.units'),
            'decimalUnits' => config('shop.decimal_units'),
            'channels' => Channel::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }
}
