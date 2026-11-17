@extends('layouts.app')

@section('title', 'Barang')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Barang</h1>
        <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">Tambah barang</a>
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-sm-6">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau kode" aria-label="Cari">
        </div>
        <div class="col-8 col-sm-4">
            <select name="status" class="form-select" aria-label="Status">
                <option value="">Semua</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-4 col-sm-2">
            <button type="submit" class="btn btn-outline-primary w-100">Cari</button>
        </div>
    </form>

    @if ($products->isEmpty())
        <p class="text-muted">Belum ada barang.</p>
    @else
        <div class="list-group mb-3">
            @foreach ($products as $product)
                <a href="{{ route('products.show', $product) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">{{ $product->name }}</div>
                            <small class="text-muted">{{ $product->unit }}</small>
                        </div>
                        <div class="text-end">
                            <div>{{ $product->currentPrice ? \App\Support\Format::rupiah($product->currentPrice->sell_price) : '-' }}</div>
                            @include('products._badge', ['status' => $product->status])
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $products->links() }}
    @endif
@endsection
