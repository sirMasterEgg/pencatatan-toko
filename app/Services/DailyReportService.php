<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    public function forDate(CarbonInterface $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $confirmed = fn () => Transaction::query()
            ->where('status', TransactionStatus::Confirmed)
            ->whereBetween('transacted_at', [$start, $end]);

        $revenue = (int) $confirmed()->sum('total');
        $count = $confirmed()->count();

        $unpaid = $confirmed()->whereIn('payment_status', [PaymentStatus::Unpaid, PaymentStatus::Partial]);
        $unpaidTotal = (int) (clone $unpaid)->sum('total') - (int) (clone $unpaid)->sum('paid_amount');
        $unpaidCount = (clone $unpaid)->count();

        $cancelledCount = Transaction::query()
            ->where('status', TransactionStatus::Cancelled)
            ->whereBetween('transacted_at', [$start, $end])
            ->count();

        $byChannel = collect(Channel::cases())->map(function (Channel $channel) use ($confirmed) {
            $query = $confirmed()->where('channel', $channel);

            return [
                'channel' => $channel,
                'count' => (clone $query)->count(),
                'revenue' => (int) (clone $query)->sum('total'),
            ];
        })->all();

        $topProducts = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('products', 'products.id', '=', 'transaction_items.product_id')
            ->where('transactions.status', TransactionStatus::Confirmed->value)
            ->whereBetween('transactions.transacted_at', [$start, $end])
            ->groupBy('transaction_items.product_id', 'products.name', 'products.unit')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get([
                'transaction_items.product_id',
                'products.name',
                'products.unit',
                DB::raw('SUM(transaction_items.quantity) as quantity'),
                DB::raw('SUM(transaction_items.subtotal) as revenue'),
            ])
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'unit' => $row->unit,
                'quantity' => (float) $row->quantity,
                'revenue' => (int) $row->revenue,
            ])
            ->all();

        $newProductsCount = Product::query()
            ->where('status', ProductStatus::NeedsReview)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            'date' => $date->copy(),
            'revenue' => $revenue,
            'transaction_count' => $count,
            'average' => $count > 0 ? (int) round($revenue / $count) : 0,
            'unpaid_total' => $unpaidTotal,
            'unpaid_count' => $unpaidCount,
            'cancelled_count' => $cancelledCount,
            'by_channel' => $byChannel,
            'top_products' => $topProducts,
            'new_products_count' => $newProductsCount,
            'transactions' => $confirmed()->with('customer')->orderByDesc('transacted_at')->orderByDesc('id')->get(),
        ];
    }
}
