@extends('layouts.app')

@section('title', 'Laporan harian')

@section('content')
    <h1 class="h4 mb-3">Laporan harian</h1>

    <form method="GET" action="{{ route('reports.daily') }}" class="d-flex flex-wrap gap-2 mb-3">
        <a href="{{ route('reports.daily', ['date' => $previousDate->format('Y-m-d')]) }}" class="btn btn-outline-secondary">‹ Sebelumnya</a>
        <input type="date" name="date" value="{{ $report['date']->format('Y-m-d') }}" class="form-control w-auto" aria-label="Tanggal">
        <button type="submit" class="btn btn-primary">Lihat</button>
        @unless ($isToday)
            <a href="{{ route('reports.daily', ['date' => $nextDate->format('Y-m-d')]) }}" class="btn btn-outline-secondary">Berikutnya ›</a>
        @endunless
    </form>
    @error('date') <div class="alert alert-danger">{{ $message }}</div> @enderror

    <p class="text-muted">{{ $report['date']->format('d/m/Y') }}</p>

    @include('reports._summary')

    @if ($report['new_products_count'] > 0)
        <div class="alert alert-warning">
            {{ $report['new_products_count'] }} barang baru perlu dirapikan.
            <a href="{{ url('/products?status=needs_review') }}">Lihat</a>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Per saluran</h2>
            <ul class="list-group list-group-flush">
                @foreach ($report['by_channel'] as $row)
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>{{ $row['channel']->label() }} <small class="text-muted">({{ $row['count'] }} transaksi)</small></span>
                        <span>{{ \App\Support\Format::rupiah($row['revenue']) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Barang terlaris</h2>
            @if (empty($report['top_products']))
                <p class="text-muted mb-0">Belum ada penjualan.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Barang</th><th>Jumlah</th><th class="text-end">Omzet</th></tr></thead>
                        <tbody>
                            @foreach ($report['top_products'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ \App\Support\Format::quantity($row['quantity']) }} {{ $row['unit'] }}</td>
                                    <td class="text-end">{{ \App\Support\Format::rupiah($row['revenue']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Transaksi</h2>
            @if ($report['transactions']->isEmpty())
                <p class="text-muted mb-0">Belum ada transaksi pada tanggal ini.</p>
            @else
                <div class="list-group list-group-flush">
                    @foreach ($report['transactions'] as $transaction)
                        <a href="{{ route('transactions.show', $transaction) }}" class="list-group-item list-group-item-action px-0">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold">{{ $transaction->number }}</span>
                                <span>{{ \App\Support\Format::rupiah($transaction->total) }}</span>
                            </div>
                            <small class="text-muted">{{ $transaction->transacted_at->format('H:i') }} · {{ $transaction->customer?->displayName() ?? 'Pembeli umum' }}</small>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
