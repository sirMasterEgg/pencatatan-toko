@extends('layouts.app')

@section('title', $customer->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="h4 mb-0">{{ $customer->name }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
            <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Hapus pelanggan ini?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Hapus</button>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Nomor HP</dt><dd class="col-7">{{ $customer->phone ?: '-' }}</dd>
                <dt class="col-5">Alamat</dt><dd class="col-7">{{ $customer->address ?: '-' }}</dd>
                <dt class="col-5">Catatan</dt><dd class="col-7">{{ $customer->note ?: '-' }}</dd>
                <dt class="col-5">Jumlah transaksi</dt><dd class="col-7">{{ $transactionCount }}</dd>
                <dt class="col-5">Total belanja</dt><dd class="col-7">{{ \App\Support\Format::rupiah($totalSpent) }}</dd>
            </dl>
        </div>
    </div>

    <h2 class="h6">Transaksi terakhir</h2>
    @if ($transactions->isEmpty())
        <p class="text-muted">Belum ada transaksi.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped bg-white">
                <thead>
                    <tr><th>Tanggal</th><th>Nomor</th><th>Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transacted_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if (Route::has('transactions.show'))
                                    <a href="{{ route('transactions.show', $transaction) }}">{{ $transaction->number }}</a>
                                @else
                                    {{ $transaction->number }}
                                @endif
                            </td>
                            <td>{{ \App\Support\Format::rupiah($transaction->total) }}</td>
                            <td>
                                @php($class = match ($transaction->status) {
                                    \App\Enums\TransactionStatus::Confirmed => 'text-bg-success',
                                    \App\Enums\TransactionStatus::Cancelled => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                })
                                <span class="badge {{ $class }}">{{ $transaction->status->label() }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
