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

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h6">Harga khusus</h2>
            @if ($specialProducts->isEmpty())
                <p class="text-muted">Belum ada harga khusus.</p>
            @else
                <ul class="list-group mb-3">
                    @foreach ($specialProducts as $product)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <a href="{{ route('products.show', $product) }}" class="fw-semibold">{{ $product->name }}</a>
                                <small class="text-muted">({{ $product->unit }})</small>
                                <div class="small text-muted">Harga umum: {{ $product->currentPrice ? \App\Support\Format::rupiah($product->currentPrice->sell_price) : '-' }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">{{ \App\Support\Format::rupiah($specialPrices[$product->id]) }}</div>
                                <form method="POST" action="{{ route('customers.prices.stop', [$customer, $product]) }}" onsubmit="return confirm('Hentikan harga khusus barang ini?')">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">Hentikan</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('customers.prices.store', $customer) }}">
                @csrf
                <div class="mb-2">
                    <label for="product_id" class="form-label">Barang</label>
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">Pilih barang</option>
                        @foreach ($selectableProducts as $option)
                            <option value="{{ $option->id }}" @selected((int) old('product_id') === $option->id)>{{ $option->name }} ({{ $option->unit }}){{ $option->currentPrice ? ' - '.\App\Support\Format::rupiah($option->currentPrice->sell_price) : '' }}</option>
                        @endforeach
                    </select>
                    @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label for="special_sell_price" class="form-label">Harga pelanggan</label>
                    <input type="text" id="special_sell_price" name="sell_price" value="{{ \App\Support\Format::thousands(old('sell_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_price') is-invalid @enderror" required>
                    @error('sell_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-2">
                    <label for="special_note" class="form-label">Catatan</label>
                    <input type="text" id="special_note" name="note" value="{{ old('note') }}" class="form-control @error('note') is-invalid @enderror">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text mb-2">Jika barang sudah punya harga khusus, harga lama diganti mulai sekarang.</div>
                <button type="submit" class="btn btn-primary w-100">Simpan harga khusus</button>
            </form>
        </div>
    </div>

    @if ($priceHistory->isNotEmpty())
        <h2 class="h6">Riwayat harga khusus</h2>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped bg-white">
                <thead>
                    <tr><th>Berlaku sejak</th><th>Barang</th><th>Harga</th><th>Catatan</th></tr>
                </thead>
                <tbody>
                    @foreach ($priceHistory as $row)
                        <tr>
                            <td>{{ $row->effective_from->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->product->name }}</td>
                            <td>{{ $row->sell_price !== null ? \App\Support\Format::rupiah($row->sell_price) : 'Dihentikan' }}</td>
                            <td>{{ $row->note ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
