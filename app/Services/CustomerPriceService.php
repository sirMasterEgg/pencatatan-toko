<?php

namespace App\Services;

use App\Models\CustomerPrice;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CustomerPriceService
{
    /**
     * Harga pelanggan yang berlaku untuk satu barang pada waktu tertentu (bawaan: sekarang).
     * Mengembalikan null jika tidak ada atau sudah dihentikan.
     */
    public function priceAt(int $customerId, int $productId, ?CarbonInterface $at = null): ?int
    {
        return CustomerPrice::query()
            ->where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('effective_from', '<=', $at ?? now())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first()
            ?->sell_price;
    }

    /**
     * Semua harga khusus yang berlaku untuk satu pelanggan: [product_id => sell_price].
     */
    public function activeForCustomer(int $customerId, ?CarbonInterface $at = null): array
    {
        return $this->latestByKey(
            CustomerPrice::query()->where('customer_id', $customerId),
            'product_id',
            $at,
        );
    }

    /**
     * Semua pelanggan yang punya harga khusus berlaku untuk satu barang: [customer_id => sell_price].
     */
    public function activeForProduct(int $productId, ?CarbonInterface $at = null): array
    {
        return $this->latestByKey(
            CustomerPrice::query()->where('product_id', $productId),
            'customer_id',
            $at,
        );
    }

    public function set(
        int $customerId,
        int $productId,
        int $sellPrice,
        ?string $note = null,
        ?int $sourceTransactionId = null,
        ?CarbonInterface $effectiveFrom = null,
    ): CustomerPrice {
        return CustomerPrice::create([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'sell_price' => $sellPrice,
            'effective_from' => $effectiveFrom ?? now(),
            'source_transaction_id' => $sourceTransactionId,
            'note' => $note,
        ]);
    }

    public function stop(int $customerId, int $productId): CustomerPrice
    {
        return CustomerPrice::create([
            'customer_id' => $customerId,
            'product_id' => $productId,
            'sell_price' => null,
            'effective_from' => now(),
            'note' => 'Harga khusus dihentikan',
        ]);
    }

    /**
     * Ambil baris terbaru per $key yang sudah berlaku, lalu buang yang sudah dihentikan.
     */
    private function latestByKey(Builder $query, string $key, ?CarbonInterface $at): array
    {
        $rows = $query
            ->where('effective_from', '<=', $at ?? now())
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get([$key, 'sell_price']);

        $prices = [];
        foreach ($rows as $row) {
            $prices[$row->{$key}] = $row->sell_price;
        }

        return array_filter($prices, fn (?int $price) => $price !== null);
    }
}
