@php($editing = isset($product))
<div class="mb-3">
    <label for="name" class="form-label">Nama</label>
    <input type="text" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="unit" class="form-label">Satuan</label>
    <select id="unit" name="unit" class="form-select @error('unit') is-invalid @enderror" required>
        <option value="">Pilih satuan</option>
        @foreach (config('shop.units') as $unit)
            <option value="{{ $unit }}" @selected(old('unit', $product->unit ?? '') === $unit)>{{ $unit }}</option>
        @endforeach
    </select>
    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="category" class="form-label">Kategori</label>
    <input type="text" id="category" name="category" value="{{ old('category', $product->category ?? '') }}" class="form-control @error('category') is-invalid @enderror">
    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="code" class="form-label">Kode</label>
    <input type="text" id="code" name="code" value="{{ old('code', $product->code ?? '') }}" class="form-control @error('code') is-invalid @enderror">
    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
@unless ($editing)
    <div class="mb-3">
        <label for="sell_price" class="form-label">Harga jual</label>
        <input type="text" id="sell_price" name="sell_price" value="{{ \App\Support\Format::thousands(old('sell_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('sell_price') is-invalid @enderror" required>
        @error('sell_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label for="cost_price" class="form-label">Harga modal</label>
        <input type="text" id="cost_price" name="cost_price" value="{{ \App\Support\Format::thousands(old('cost_price')) }}" inputmode="numeric" autocomplete="off" data-rupiah class="form-control @error('cost_price') is-invalid @enderror">
        @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@endunless
@if ($editing && $product->status !== \App\Enums\ProductStatus::NeedsReview)
    <div class="mb-3">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
            <option value="active" @selected(old('status', $product->status->value) === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $product->status->value) === 'inactive')>Nonaktif</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@endif
