@extends('layouts.app')

@section('title', 'Ubah harga massal')

@section('content')
    <h1 class="h4 mb-3">Ubah harga massal</h1>

    <form method="GET" action="{{ route('products.bulk-price.create') }}" class="d-flex gap-2 mb-3">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Cari nama atau kode" aria-label="Cari">
        <button type="submit" class="btn btn-outline-primary">Cari</button>
    </form>

    @if ($errors->has('product_ids'))
        <div class="alert alert-danger">
            @foreach ($errors->get('product_ids') as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    @if ($products->isEmpty())
        <p class="text-muted">Tidak ada barang yang cocok.</p>
    @else
        <form method="POST" action="{{ route('products.bulk-price.preview') }}">
            @csrf
            <div class="card mb-3">
                <div class="card-body">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="check-all" data-check-all="input[name='product_ids[]']">
                        <label class="form-check-label fw-semibold" for="check-all">Pilih semua ({{ $products->count() }})</label>
                    </div>
                    <div class="list-group">
                        @foreach ($products as $product)
                            <label class="list-group-item d-flex gap-2 align-items-start">
                                <input class="form-check-input mt-1" type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, array_map('intval', old('product_ids', [])), true))>
                                <span class="flex-fill">
                                    <span class="fw-semibold">{{ $product->name }}</span>
                                    <small class="text-muted">({{ $product->unit }})</small>
                                </span>
                                <span>{{ \App\Support\Format::rupiah($product->currentPrice->sell_price) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-2">
                        <label for="direction" class="form-label">Arah perubahan</label>
                        <select id="direction" name="direction" class="form-select @error('direction') is-invalid @enderror">
                            <option value="up" @selected(old('direction', 'up') === 'up')>Naik</option>
                            <option value="down" @selected(old('direction') === 'down')>Turun</option>
                        </select>
                        @error('direction')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label for="sell_amount" class="form-label">Perubahan harga jual</label>
                        <input type="text" id="sell_amount" name="sell_amount" value="{{ \App\Support\Format::thousands(old('sell_amount')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_amount') is-invalid @enderror" required>
                        @error('sell_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-2">
                        <label for="cost_amount" class="form-label">Perubahan harga modal</label>
                        <input type="text" id="cost_amount" name="cost_amount" value="{{ \App\Support\Format::thousands(old('cost_amount')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('cost_amount') is-invalid @enderror">
                        @error('cost_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Kosongkan jika harga modal tetap.</div>
                    </div>
                    <div class="mb-2">
                        <label for="effective_from" class="form-label">Berlaku mulai</label>
                        <input type="datetime-local" id="effective_from" name="effective_from" value="{{ old('effective_from', now()->format('Y-m-d\TH:i')) }}" class="form-control @error('effective_from') is-invalid @enderror" required>
                        @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="include_customer_prices" value="0">
                        <input class="form-check-input" type="checkbox" id="include_customer_prices" name="include_customer_prices" value="1" @checked(old('include_customer_prices', '1') === '1')>
                        <label class="form-check-label" for="include_customer_prices">Ubah juga harga pelanggan</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Lihat pratinjau</button>
                </div>
            </div>
        </form>
    @endif
@endsection
