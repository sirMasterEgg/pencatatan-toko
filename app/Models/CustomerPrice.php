<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'product_id', 'sell_price', 'effective_from', 'source_transaction_id', 'note'])]
class CustomerPrice extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Riwayat harga pelanggan tidak boleh diubah.'));
        static::deleting(fn () => throw new \LogicException('Riwayat harga pelanggan tidak boleh dihapus.'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'sell_price' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
