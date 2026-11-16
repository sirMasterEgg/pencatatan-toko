<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::query()->with('customer');

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $query->where(function ($query) use ($q) {
                $query->where('number', 'like', '%'.$q.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%'));
            });
        }

        if ($from = $this->parseDate($request->query('from'))) {
            $query->where('transacted_at', '>=', $from->startOfDay());
        }

        if ($to = $this->parseDate($request->query('to'))) {
            $query->where('transacted_at', '<=', $to->endOfDay());
        }

        if ($status = TransactionStatus::tryFrom((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($paymentStatus = PaymentStatus::tryFrom((string) $request->query('payment_status', ''))) {
            $query->where('payment_status', $paymentStatus);
        }

        return view('transactions.index', [
            'transactions' => $query->orderByDesc('transacted_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'statuses' => TransactionStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('transactions.create');
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load('items.product', 'customer');

        return view('transactions.show', [
            'transaction' => $transaction,
            'paymentStatuses' => PaymentStatus::cases(),
        ]);
    }

    public function cancel(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $service->cancel($transaction, $request->input('reason'));

        return redirect()->route('transactions.show', $transaction)->with('success', 'Transaksi dibatalkan.');
    }

    public function updatePaymentStatus(Request $request, Transaction $transaction, TransactionService $service): RedirectResponse
    {
        $request->validate(['payment_status' => ['required', Rule::enum(PaymentStatus::class)]]);

        $service->updatePaymentStatus($transaction, PaymentStatus::from($request->input('payment_status')));

        return redirect()->route('transactions.show', $transaction)->with('success', 'Status bayar diperbarui.');
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
