@extends('layouts.app')

@section('title', $transaction->number)

@section('content')
    @php($cancelled = $transaction->status === \App\Enums\TransactionStatus::Cancelled)
    @php($draft = $transaction->status === \App\Enums\TransactionStatus::Draft)
    <h1 class="h4 mb-3">{{ $transaction->number }}</h1>

    @if ($draft)
        <div class="alert alert-secondary">Ini draf. Draf belum masuk laporan sampai disimpan sebagai transaksi.</div>
    @endif

    @if ($cancelled)
        <div class="alert alert-danger">
            Dibatalkan pada {{ $transaction->cancelled_at->format('d/m/Y H:i') }}@if ($transaction->cancel_reason): {{ $transaction->cancel_reason }}@endif
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-5">Tanggal</dt><dd class="col-7">{{ $transaction->transacted_at->format('d/m/Y H:i') }}</dd>
                <dt class="col-5">Saluran</dt><dd class="col-7">{{ $transaction->channel->label() }}</dd>
                <dt class="col-5">Status</dt><dd class="col-7">{{ $transaction->status->label() }}</dd>
                <dt class="col-5">Status bayar</dt><dd class="col-7">@include('transactions._payment_badge', ['status' => $transaction->payment_status])</dd>
                <dt class="col-5">Pelanggan</dt>
                <dd class="col-7">
                    @if ($transaction->customer)
                        <a href="{{ route('customers.show', $transaction->customer) }}">{{ $transaction->customer->displayName() }}</a>
                        @if ($transaction->customer->phone)<div class="small text-muted">{{ $transaction->customer->phone }}</div>@endif
                    @else
                        Pembeli umum
                    @endif
                </dd>
                @if ($transaction->note)
                    <dt class="col-5">Catatan</dt><dd class="col-7">{{ $transaction->note }}</dd>
                @endif
            </dl>
        </div>
    </div>

    <ul class="list-group mb-3">
        @foreach ($transaction->items as $item)
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="{{ route('products.show', $item->product) }}" class="fw-semibold">{{ $item->product->name }}</a>
                        <div>{{ \App\Support\Format::quantity($item->quantity) }} {{ $item->unit }} × {{ \App\Support\Format::rupiah($item->unit_price) }}</div>
                        @if ($item->list_price === null)
                            <span class="badge text-bg-warning">Barang baru</span>
                        @elseif ($item->customer_price !== null)
                            <small class="text-muted">Harga pelanggan: {{ \App\Support\Format::rupiah($item->customer_price) }} · Harga umum: {{ \App\Support\Format::rupiah($item->list_price) }}</small>
                        @elseif ($item->list_price !== $item->unit_price)
                            <small class="text-muted">Harga daftar: {{ \App\Support\Format::rupiah($item->list_price) }}</small>
                        @endif
                        @if ($item->discount > 0)
                            <div class="small text-muted">Diskon: {{ \App\Support\Format::rupiah($item->discount) }}</div>
                        @endif
                    </div>
                    <div class="fw-semibold">{{ \App\Support\Format::rupiah($item->subtotal) }}</div>
                </div>
            </li>
        @endforeach
        <li class="list-group-item d-flex justify-content-between">
            <strong>Total</strong>
            <strong>{{ \App\Support\Format::rupiah($transaction->total) }}</strong>
        </li>
    </ul>

    @if ($transaction->isEditable())
        <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-primary w-100 mb-3">Ubah transaksi</a>
    @endif

    @if ($draft)
        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" onsubmit="return confirm('Hapus draf ini? Tindakan ini tidak bisa dibatalkan.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger w-100">Hapus draf</button>
        </form>
    @elseif (! $cancelled)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('transactions.payment-status', $transaction) }}" class="mb-3">
                    @csrf
                    <label for="payment_status" class="form-label">Status bayar</label>
                    <select id="payment_status" name="payment_status" class="form-select mb-2">
                        @foreach ($paymentStatuses as $status)
                            <option value="{{ $status->value }}" @selected($transaction->payment_status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-primary w-100">Simpan status bayar</button>
                </form>

                <form method="POST" action="{{ route('transactions.cancel', $transaction) }}" onsubmit="return confirm('Batalkan transaksi ini? Tindakan ini tidak bisa dibatalkan.')">
                    @csrf
                    <input type="text" name="reason" class="form-control mb-2" placeholder="Alasan pembatalan (opsional)" aria-label="Alasan pembatalan">
                    <button type="submit" class="btn btn-danger w-100">Batalkan transaksi</button>
                </form>
                @if ($transaction->isEditable())
                    <p class="small text-muted mt-2 mb-0">Transaksi ini masih bisa diubah selama status bayarnya Belum bayar.</p>
                @else
                    <p class="small text-muted mt-2 mb-0">Transaksi yang sudah dibayar tidak bisa diubah. Untuk koreksi, ubah status bayar ke Belum bayar, atau batalkan lalu buat transaksi baru.</p>
                @endif
            </div>
        </div>
    @endif
@endsection
