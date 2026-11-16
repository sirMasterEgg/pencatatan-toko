@extends('layouts.app')

@section('title', 'Beranda')

@section('content')
    <h1 class="h4 mb-3">Beranda</h1>

    <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-lg w-100 mb-3">+ Transaksi baru</a>

    <h2 class="h6 text-muted">Hari ini, {{ $report['date']->format('d/m/Y') }}</h2>

    @include('reports._summary')

    @if ($needsReviewCount > 0)
        <div class="alert alert-warning">
            {{ $needsReviewCount }} barang perlu dirapikan.
            <a href="{{ url('/products?status=needs_review') }}">Lihat</a>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Transaksi terakhir hari ini</h2>
            @if ($report['transactions']->isEmpty())
                <p class="text-muted mb-0">Belum ada transaksi.</p>
            @else
                <div class="list-group list-group-flush">
                    @foreach ($report['transactions']->take(5) as $transaction)
                        <a href="{{ route('transactions.show', $transaction) }}" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $transaction->number }}</span>
                                <span>{{ \App\Support\Format::rupiah($transaction->total) }}</span>
                            </div>
                            <small class="text-muted">{{ $transaction->transacted_at->format('H:i') }} · {{ $transaction->customer?->name ?? 'Pembeli umum' }}</small>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <a href="{{ route('reports.daily') }}">Lihat laporan lengkap</a>
@endsection
