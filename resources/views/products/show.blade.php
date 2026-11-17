@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $product->name }}</h1>
            @include('products._badge', ['status' => $product->status])
        </div>
        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary btn-sm">Ubah</a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Satuan</dt><dd class="col-7">{{ $product->unit }}</dd>
                <dt class="col-5">Kategori</dt><dd class="col-7">{{ $product->category ?: '-' }}</dd>
                <dt class="col-5">Kode</dt><dd class="col-7">{{ $product->code ?: '-' }}</dd>
                <dt class="col-5">Harga jual saat ini</dt>
                <dd class="col-7">{{ $product->currentPrice ? \App\Support\Format::rupiah($product->currentPrice->sell_price) : '-' }}</dd>
            </dl>
            @if ($product->status === \App\Enums\ProductStatus::NeedsReview)
                <form method="POST" action="{{ route('products.mark-reviewed', $product) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-warning w-100">Tandai sudah diperiksa</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Tambah harga baru</h2>
            <form method="POST" action="{{ route('products.prices.store', $product) }}">
                @csrf
                <div class="mb-2">
                    <label for="sell_price" class="form-label">Harga jual</label>
                    <input type="text" id="sell_price" name="sell_price" value="{{ \App\Support\Format::thousands(old('sell_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_price') is-invalid @enderror" required>
                    @error('sell_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label for="cost_price" class="form-label">Harga modal</label>
                    <input type="text" id="cost_price" name="cost_price" value="{{ \App\Support\Format::thousands(old('cost_price', $product->currentPrice?->cost_price)) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('cost_price') is-invalid @enderror">
                    @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="note" class="form-label">Catatan</label>
                    <input type="text" id="note" name="note" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-100">Simpan harga</button>
            </form>
        </div>
    </div>

    <h2 class="h6">Riwayat harga</h2>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-striped bg-white">
            <thead>
                <tr><th>Berlaku sejak</th><th>Harga jual</th><th>Harga modal</th><th>Catatan</th></tr>
            </thead>
            <tbody>
                @forelse ($prices as $price)
                    <tr>
                        <td>{{ $price->effective_from->format('d/m/Y H:i') }}</td>
                        <td>{{ \App\Support\Format::rupiah($price->sell_price) }}</td>
                        <td>{{ $price->cost_price !== null ? \App\Support\Format::rupiah($price->cost_price) : '-' }}</td>
                        <td>{{ $price->note ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Belum ada harga.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="h6">Transaksi terakhir</h2>
    @if ($recentItems->isEmpty())
        <p class="text-muted">Belum ada transaksi.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-striped bg-white">
                <thead>
                    <tr><th>Tanggal</th><th>Nomor</th><th>Jumlah</th><th>Harga satuan</th></tr>
                </thead>
                <tbody>
                    @foreach ($recentItems as $item)
                        <tr>
                            <td>{{ $item->transaction->transacted_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $item->transaction->number }}</td>
                            <td>{{ \App\Support\Format::quantity($item->quantity) }} {{ $item->unit }}</td>
                            <td>{{ \App\Support\Format::rupiah($item->unit_price) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
