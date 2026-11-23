@extends('layouts.app')

@section('title', 'Pratinjau perubahan harga')

@section('content')
    <h1 class="h4 mb-3">Pratinjau perubahan harga</h1>

    <div class="alert alert-info">
        Harga jual {{ $sellChange >= 0 ? 'naik' : 'turun' }} {{ \App\Support\Format::rupiah(abs($sellChange)) }}.
        @if ($costChange === null)
            Harga modal tetap.
        @else
            Harga modal {{ $costChange >= 0 ? 'naik' : 'turun' }} {{ \App\Support\Format::rupiah(abs($costChange)) }}.
        @endif
        Berlaku mulai {{ $effectiveFrom->format('d/m/Y H:i') }}.
    </div>

    <ul class="list-group mb-3">
        @foreach ($rows as $row)
            <li class="list-group-item">
                <div class="fw-semibold">{{ $row['product']->name }} <small class="text-muted">({{ $row['product']->unit }})</small></div>
                <div>Harga jual: {{ \App\Support\Format::rupiah($row['old_sell']) }} → <strong>{{ \App\Support\Format::rupiah($row['new_sell']) }}</strong></div>
                @if ($row['old_cost'] !== null)
                    <div class="small text-muted">Harga modal: {{ \App\Support\Format::rupiah($row['old_cost']) }} → {{ \App\Support\Format::rupiah($row['new_cost']) }}</div>
                @endif
                @if (count($row['customer_prices']) > 0)
                    <div class="small text-muted">Harga pelanggan ikut berubah: {{ count($row['customer_prices']) }} pelanggan</div>
                @endif
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('products.bulk-price.store') }}">
        @csrf
        @foreach ($input['product_ids'] as $productId)
            <input type="hidden" name="product_ids[]" value="{{ $productId }}">
        @endforeach
        <input type="hidden" name="direction" value="{{ $input['direction'] }}">
        <input type="hidden" name="sell_amount" value="{{ $input['sell_amount'] }}">
        <input type="hidden" name="cost_amount" value="{{ $input['cost_amount'] }}">
        <input type="hidden" name="effective_from" value="{{ $input['effective_from'] }}">
        <input type="hidden" name="include_customer_prices" value="{{ $input['include_customer_prices'] ?? '0' }}">
        <button type="submit" class="btn btn-primary w-100 mb-2">Simpan perubahan harga ({{ count($rows) }} barang)</button>
        <a href="{{ route('products.bulk-price.create') }}" class="btn btn-outline-secondary w-100">Batal</a>
    </form>
@endsection
