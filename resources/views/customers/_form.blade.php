<div class="mb-3">
    <label for="name" class="form-label">Nama</label>
    <input type="text" id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="phone" class="form-label">Nomor HP</label>
    <input type="tel" inputmode="tel" id="phone" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="form-control @error('phone') is-invalid @enderror">
    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Contoh: 0812-3456-7890. Disimpan dalam format 62812...</div>
</div>
<div class="mb-3">
    <label for="address" class="form-label">Alamat</label>
    <input type="text" id="address" name="address" value="{{ old('address', $customer->address ?? '') }}" class="form-control @error('address') is-invalid @enderror">
    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="note" class="form-label">Catatan</label>
    <input type="text" id="note" name="note" value="{{ old('note', $customer->note ?? '') }}" class="form-control @error('note') is-invalid @enderror">
    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
