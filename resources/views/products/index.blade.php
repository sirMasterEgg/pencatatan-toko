@extends('layouts.app')

@section('title', 'Barang')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Barang</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('products.bulk-price.create') }}" class="btn btn-outline-primary btn-sm">Ubah harga massal</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">Tambah barang</a>
        </div>
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-12">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau kode" aria-label="Cari">
            </div>
            <div class="col-6">
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">Semua</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6">
                <select name="sort" class="form-select" aria-label="Urutkan">
                    @foreach ($sorts as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="collapse {{ $filtersOpen ? 'show' : '' }}" id="productFilters">
            <div class="row g-2 mt-0">
                <div class="col-6">
                    <label for="filter-unit" class="form-label small mb-0">Satuan</label>
                    <select id="filter-unit" name="unit" class="form-select">
                        <option value="">Semua satuan</option>
                        @foreach (config('shop.units') as $unit)
                            <option value="{{ $unit }}" @selected(request('unit') === $unit)>{{ $unit }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label for="filter-category" class="form-label small mb-0">Kategori</label>
                    <select id="filter-category" name="category" class="form-select">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6">
                    <label for="filter-min-price" class="form-label small mb-0">Harga minimal</label>
                    <input type="text" id="filter-min-price" name="min_price" value="{{ \App\Support\Format::thousands(request('min_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('min_price') is-invalid @enderror">
                    @error('min_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label for="filter-max-price" class="form-label small mb-0">Harga maksimal</label>
                    <input type="text" id="filter-max-price" name="max_price" value="{{ \App\Support\Format::thousands(request('max_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('max_price') is-invalid @enderror">
                    @error('max_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 align-items-center mt-2">
            <button type="submit" class="btn btn-outline-primary">Cari</button>
            <button type="button" class="btn btn-link px-0" data-bs-toggle="collapse" data-bs-target="#productFilters" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="productFilters">Filter lainnya</button>
            <a href="{{ route('products.index') }}" class="ms-auto">Reset</a>
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
                            @if ($sort === 'best_selling')
                                <small class="text-muted">· Terjual {{ \App\Support\Format::quantity($product->sold_quantity) }}</small>
                            @endif
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
