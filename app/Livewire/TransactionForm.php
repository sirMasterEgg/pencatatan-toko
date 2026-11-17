<?php

namespace App\Livewire;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Services\TransactionService;
use App\Support\Parse;
use App\Support\Phone;
use Illuminate\Support\Str;
use Livewire\Component;

class TransactionForm extends Component
{
    public string $transactedAt = '';

    public string $channel = 'store';

    public string $paymentStatus = 'paid';

    public string $note = '';

    public string $customerMode = 'none';

    public ?int $customerId = null;

    public string $customerLabel = '';

    public string $customerSearch = '';

    public array $customerResults = [];

    public string $customerName = '';

    public string $customerPhone = '';

    public array $items = [];

    public function mount(): void
    {
        $this->transactedAt = now()->format('Y-m-d\TH:i');
        $this->items = [$this->emptyRow()];
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

    private function searchProducts(string $term): array
    {
        return Product::selectable()
            ->with('currentPrice')
            ->where('name', 'like', '%'.$term.'%')
            ->orderBy('name')
            ->limit(8)
            ->get()
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
            ->map(fn (Customer $c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])
            ->all();
    }

    public function selectProduct(int $index, int $productId): void
    {
        $product = Product::selectable()->with('currentPrice')->find($productId);

        if (! $product || ! isset($this->items[$index])) {
            return;
        }

        $price = $product->currentPrice?->sell_price;

        $this->items[$index] = array_merge($this->items[$index], [
            'mode' => 'existing',
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'list_price' => $price,
            'unit_price' => $price,
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
        $this->customerLabel = $customer->phone ? $customer->name.' ('.$customer->phone.')' : $customer->name;
        $this->customerSearch = '';
        $this->customerResults = [];
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
    }

    public function rowSubtotal(array $row): int
    {
        $quantity = Parse::quantity($row['quantity'] ?? null);
        $unitPrice = $row['unit_price'] ?? null;

        if (! is_numeric($quantity) || ! is_numeric($unitPrice)) {
            return 0;
        }

        $discount = is_numeric($row['discount'] ?? null) ? (int) $row['discount'] : 0;

        return TransactionService::calculateSubtotal($quantity, (int) $unitPrice, $discount);
    }

    public function total(): int
    {
        return array_sum(array_map(fn (array $row) => $this->rowSubtotal($row), $this->items));
    }

    public function save(TransactionService $service)
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

        $toInt = fn ($v) => is_numeric($v) ? (int) $v : ($v === '' ? null : $v);

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

        $transaction = $service->create([
            'transacted_at' => str_replace('T', ' ', $this->transactedAt),
            'channel' => $this->channel,
            'payment_status' => $this->paymentStatus,
            'note' => $this->note !== '' ? $this->note : null,
            'customer' => $customer,
            'items' => $items,
        ]);

        session()->flash('success', 'Transaksi '.$transaction->number.' berhasil disimpan.');

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
