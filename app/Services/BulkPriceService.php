<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkPriceService
{
    public function __construct(
        private ProductService $products,
        private CustomerPriceService $customerPrices,
    ) {}

    /**
     * Hitung harga lama dan baru untuk setiap barang terpilih, tanpa menyimpan apa pun.
     * Barang yang tidak aktif atau belum punya harga dilewati.
     *
     * @param  array<int>  $productIds
     * @return array<int, array{product: Product, old_sell: int, new_sell: int, old_cost: ?int, new_cost: ?int, customer_prices: array<int, array{old: int, new: int}>}>
     */
    public function preview(array $productIds, int $sellChange, ?int $costChange, CarbonInterface $effectiveFrom, bool $includeCustomerPrices): array
    {
        $effectiveFrom = $this->notInPast($effectiveFrom);
        $rows = [];

        $products = Product::selectable()->whereKey($productIds)->orderBy('name')->get();

        foreach ($products as $product) {
            $base = $this->priceAt($product, $effectiveFrom);

            if ($base === null) {
                continue;
            }

            $customerPrices = [];
            if ($includeCustomerPrices) {
                foreach ($this->customerPrices->activeForProduct($product->id, $effectiveFrom) as $customerId => $price) {
                    $customerPrices[$customerId] = ['old' => $price, 'new' => $price + $sellChange];
                }
            }

            $rows[] = [
                'product' => $product,
                'old_sell' => $base->sell_price,
                'new_sell' => $base->sell_price + $sellChange,
                'old_cost' => $base->cost_price,
                'new_cost' => $base->cost_price === null || $costChange === null ? $base->cost_price : $base->cost_price + $costChange,
                'customer_prices' => $customerPrices,
            ];
        }

        return $rows;
    }

    /**
     * Lempar ValidationException jika ada harga yang akan menjadi di bawah Rp0.
     */
    public function assertValid(array $rows): void
    {
        $errors = [];

        foreach ($rows as $row) {
            $name = $row['product']->name;

            if ($row['new_sell'] < 0) {
                $errors[] = "Harga jual {$name} akan menjadi di bawah Rp0.";
            }
            if ($row['new_cost'] !== null && $row['new_cost'] < 0) {
                $errors[] = "Harga modal {$name} akan menjadi di bawah Rp0.";
            }
            foreach ($row['customer_prices'] as $price) {
                if ($price['new'] < 0) {
                    $errors[] = "Harga pelanggan {$name} akan menjadi di bawah Rp0.";
                    break;
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages(['product_ids' => $errors]);
        }
    }

    /**
     * Simpan perubahan harga. Mengembalikan jumlah barang yang harganya diubah.
     */
    public function apply(array $productIds, int $sellChange, ?int $costChange, CarbonInterface $effectiveFrom, bool $includeCustomerPrices): int
    {
        $effectiveFrom = $this->notInPast($effectiveFrom);
        $rows = $this->preview($productIds, $sellChange, $costChange, $effectiveFrom, $includeCustomerPrices);
        $this->assertValid($rows);

        $sign = $sellChange >= 0 ? '+' : '-';
        $note = 'Perubahan harga massal '.$sign.Format::rupiah(abs($sellChange));

        DB::transaction(function () use ($rows, $note, $effectiveFrom) {
            foreach ($rows as $row) {
                $this->products->addPrice($row['product'], $row['new_sell'], $row['new_cost'], $note, null, $effectiveFrom);

                foreach ($row['customer_prices'] as $customerId => $price) {
                    $this->customerPrices->set($customerId, $row['product']->id, $price['new'], $note, null, $effectiveFrom);
                }
            }
        });

        return count($rows);
    }

    /**
     * Harga tidak boleh berlaku mundur. Waktu yang sudah lewat diganti dengan sekarang.
     */
    private function notInPast(CarbonInterface $effectiveFrom): CarbonInterface
    {
        return $effectiveFrom->isPast() ? now() : $effectiveFrom;
    }

    /**
     * Baris harga umum yang berlaku pada waktu tertentu.
     */
    private function priceAt(Product $product, CarbonInterface $at): ?ProductPrice
    {
        return $product->prices()
            ->where('effective_from', '<=', $at)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }
}
