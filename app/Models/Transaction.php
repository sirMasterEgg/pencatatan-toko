<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'transacted_at', 'customer_id', 'channel', 'input_source', 'status', 'payment_status', 'total', 'note', 'cancelled_at', 'cancel_reason'])]
class Transaction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transacted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => TransactionStatus::class,
            'payment_status' => PaymentStatus::class,
            'channel' => Channel::class,
            'total' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Isi transaksi boleh diubah jika masih draf, atau sudah dikonfirmasi tetapi belum dibayar sama sekali.
     */
    public function isEditable(): bool
    {
        return $this->status === TransactionStatus::Draft
            || ($this->status === TransactionStatus::Confirmed && $this->payment_status === PaymentStatus::Unpaid);
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', TransactionStatus::Confirmed);
    }
}
