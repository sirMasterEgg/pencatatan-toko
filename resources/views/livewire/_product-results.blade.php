{{-- Hasil cari barang, sudah diurutkan per satuan lalu nama oleh TransactionForm::searchProducts. --}}
<div class="list-group mt-2">
    @php($currentUnit = null)
    @foreach ($results as $r)
        @if ($r['unit'] !== $currentUnit)
            @php($currentUnit = $r['unit'])
            <div class="list-group-item list-group-item-secondary py-1 small fw-semibold" wire:key="{{ $keyPrefix }}-unit-{{ $r['unit'] }}">{{ $r['unit'] }}</div>
        @endif
        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between" wire:key="{{ $keyPrefix }}-{{ $r['id'] }}" wire:click="selectProduct({{ $i }}, {{ $r['id'] }})">
            <span>{{ $r['name'] }}</span>
            <small class="text-muted">{{ \App\Support\Format::rupiah($r['price']) }}</small>
        </button>
    @endforeach
</div>
