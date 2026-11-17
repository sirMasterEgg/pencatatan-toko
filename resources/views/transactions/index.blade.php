@extends('layouts.app')

@section('title', 'Transaksi')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Transaksi</h1>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm">Transaksi baru</a>
    </div>

    <form method="GET" action="{{ route('transactions.index') }}" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-12">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nomor atau nama pelanggan" aria-label="Cari">
            </div>
            <div class="col-6">
                <label for="from" class="form-label small mb-0">Dari</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-6">
                <label for="to" class="form-label small mb-0">Sampai</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-6">
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <select name="payment_status" class="form-select" aria-label="Status bayar">
                    <option value="">Semua status bayar</option>
                    @foreach ($paymentStatuses as $status)
                        <option value="{{ $status->value }}" @selected(request('payment_status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button type="submit" class="btn btn-outline-primary">Terapkan</button>
                <a href="{{ route('transactions.index') }}">Reset</a>
            </div>
        </div>
    </form>

    @if ($transactions->isEmpty())
        <p class="text-muted">Belum ada transaksi.</p>
    @else
        <div class="list-group mb-3">
            @foreach ($transactions as $transaction)
                @php($cancelled = $transaction->status === \App\Enums\TransactionStatus::Cancelled)
                <a href="{{ route('transactions.show', $transaction) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">{{ $transaction->number }}</span>
                        <span class="{{ $cancelled ? 'text-decoration-line-through' : '' }}">{{ \App\Support\Format::rupiah($transaction->total) }}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center small text-muted">
                        <span>{{ $transaction->transacted_at->format('d/m/Y H:i') }}</span>
                        <span>{{ $transaction->customer?->name ?? 'Pembeli umum' }}</span>
                        @if ($cancelled)
                            <span class="badge text-bg-danger">{{ $transaction->status->label() }}</span>
                        @endif
                        @include('transactions._payment_badge', ['status' => $transaction->payment_status])
                    </div>
                </a>
            @endforeach
        </div>
        {{ $transactions->links() }}
    @endif
@endsection
