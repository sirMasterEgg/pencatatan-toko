<div>
    <form wire:submit="save">
        @error('status') <div class="alert alert-danger">{{ $message }}</div> @enderror

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Data transaksi</h2>
                <div class="mb-2">
                    <label for="transactedAt" class="form-label">Tanggal</label>
                    <input type="datetime-local" id="transactedAt" class="form-control @error('transacted_at') is-invalid @enderror" wire:model.live="transactedAt" @if ($autoTime) data-live-clock @endif>
                    @error('transacted_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if ($autoTime)
                        <div class="form-text">Mengikuti waktu sekarang. Ubah jika transaksi terjadi di waktu lain.</div>
                    @else
                        <button type="button" class="btn btn-link btn-sm px-0" wire:click="useCurrentTime">Pakai waktu sekarang</button>
                    @endif
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label for="channel" class="form-label">Saluran</label>
                        <select id="channel" class="form-select" wire:model="channel">
                            @foreach ($channels as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="paymentStatus" class="form-label">Status bayar</label>
                        <select id="paymentStatus" class="form-select" wire:model.live="paymentStatus">
                            @foreach ($paymentStatuses as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @if ($paymentStatus === 'partial')
                    <div class="mt-2">
                        <label for="paidAmount" class="form-label">Nominal dibayar</label>
                        <input type="text" id="paidAmount" class="form-control @error('paid_amount') is-invalid @enderror" wire:model.live.blur="paidAmount" inputmode="numeric" autocomplete="off" data-rupiah>
                        @error('paid_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Kurang: <strong>{{ \App\Support\Format::rupiah($this->remaining()) }}</strong></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Pelanggan</h2>
                @if ($customerMode === 'none')
                    <input type="text" class="form-control" aria-label="Cari pelanggan" placeholder="Cari nama atau nomor HP (kosongkan untuk pembeli umum)" wire:model.live.debounce.300ms="customerSearch">
                    @if (count($customerResults) > 0)
                        <div class="list-group mt-2">
                            @foreach ($customerResults as $c)
                                <button type="button" class="list-group-item list-group-item-action" wire:key="cust-{{ $c['id'] }}" wire:click="selectCustomer({{ $c['id'] }})">
                                    {{ $c['label'] }}@if ($c['phone']) <small class="text-muted">({{ $c['phone'] }})</small>@endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @if (mb_strlen(trim($customerSearch)) >= 2)
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" wire:click="startNewCustomer">+ Pelanggan baru</button>
                    @endif
                @elseif ($customerMode === 'existing')
                    <div class="d-flex justify-content-between align-items-center">
                        <span>{{ $customerLabel }}</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearCustomer">Ganti</button>
                    </div>
                @else
                    <div class="mb-2">
                        <label for="customerName" class="form-label">Nama</label>
                        <input type="text" id="customerName" class="form-control text-uppercase @error('customer.name') is-invalid @enderror" autocapitalize="characters" wire:model="customerName">
                        @error('customer.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-2">
                        <label for="customerPhone" class="form-label">Nomor HP</label>
                        <input type="tel" id="customerPhone" class="form-control" wire:model="customerPhone">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="clearCustomer">Batal</button>
                @endif
            </div>
        </div>

        <h2 class="h6">Barang</h2>
        @foreach ($items as $i => $row)
            <div class="card mb-3" wire:key="row-{{ $row['key'] }}">
                <div class="card-body">
                    @if ($row['mode'] === 'search')
                        <input type="text" class="form-control @error("items.$i.search") is-invalid @enderror" aria-label="Cari barang" placeholder="Ketik nama barang" wire:model.live.debounce.300ms="items.{{ $i }}.search">
                        @error("items.$i.search") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @if (count($row['results']) > 0)
                            @include('livewire._product-results', ['results' => $row['results'], 'i' => $i, 'keyPrefix' => 'res-'.$row['key']])
                        @endif
                        @if (mb_strlen(trim($row['search'])) >= 2)
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" wire:click="startNewProduct({{ $i }})">+ Tambah "{{ $row['search'] }}" sebagai barang baru</button>
                        @endif
                    @else
                        @if ($row['mode'] === 'existing')
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-semibold">{{ $row['name'] }} <small class="text-muted">({{ $row['unit'] }})</small></div>
                                    <small class="text-muted">Harga daftar: {{ $row['list_price'] !== null ? \App\Support\Format::rupiah($row['list_price']) : '-' }}</small>
                                    @if ($row['customer_price'] !== null)
                                        <small class="text-muted d-block">Harga pelanggan: {{ \App\Support\Format::rupiah($row['customer_price']) }}</small>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetRow({{ $i }})">Ganti barang</button>
                            </div>
                            @error("items.$i.product_id") <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @else
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge text-bg-warning">Barang baru</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="resetRow({{ $i }})">Batal</button>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="name-{{ $i }}">Nama</label>
                                <input type="text" id="name-{{ $i }}" class="form-control text-uppercase @error("items.$i.new_product.name") is-invalid @enderror" autocapitalize="characters" wire:model="items.{{ $i }}.name">
                                @error("items.$i.new_product.name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label" for="unit-{{ $i }}">Satuan</label>
                                    <select id="unit-{{ $i }}" class="form-select @error("items.$i.new_product.unit") is-invalid @enderror" wire:model.live="items.{{ $i }}.unit">
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit }}">{{ $unit }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.$i.new_product.unit") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="cost-{{ $i }}">Harga modal</label>
                                    <input type="text" id="cost-{{ $i }}" class="form-control @error("items.$i.new_product.cost_price") is-invalid @enderror" wire:model="items.{{ $i }}.cost_price" inputmode="numeric" autocomplete="off" data-rupiah>
                                    @error("items.$i.new_product.cost_price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            @if (count($row['results']) > 0)
                                <div class="alert alert-warning">
                                    <strong>Barang yang mirip:</strong>
                                    @include('livewire._product-results', ['results' => $row['results'], 'i' => $i, 'keyPrefix' => 'sim-'.$row['key']])
                                </div>
                            @endif
                        @endif

                        <div class="row g-2 mb-2">
                            <div class="col-4">
                                <label class="form-label" for="qty-{{ $i }}">Jumlah</label>
                                <input type="text" id="qty-{{ $i }}" class="form-control @error("items.$i.quantity") is-invalid @enderror"
                                       wire:model.live.blur="items.{{ $i }}.quantity" inputmode="{{ in_array($row['unit'], $decimalUnits, true) ? 'decimal' : 'numeric' }}" autocomplete="off" data-clear-default="1">
                                @error("items.$i.quantity") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">{{ $row['unit'] }}</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="price-{{ $i }}">{{ $row['mode'] === 'new' ? 'Harga jual' : 'Harga satuan' }}</label>
                                <input type="text" id="price-{{ $i }}" class="form-control @error("items.$i.unit_price") is-invalid @enderror"
                                       wire:model.live.blur="items.{{ $i }}.unit_price" inputmode="numeric" autocomplete="off" data-rupiah>
                                @error("items.$i.unit_price") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-4">
                                <label class="form-label" for="disc-{{ $i }}">Diskon</label>
                                <input type="text" id="disc-{{ $i }}" class="form-control @error("items.$i.discount") is-invalid @enderror"
                                       wire:model.live.blur="items.{{ $i }}.discount" inputmode="numeric" autocomplete="off" data-rupiah data-clear-default="0">
                                @error("items.$i.discount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        @php($typedPrice = \App\Support\Parse::rupiah($row['unit_price']))
                        @php($referencePrice = $row['customer_price'] ?? $row['list_price'])
                        @if ($row['mode'] === 'existing' && $row['list_price'] !== null && is_int($typedPrice) && $typedPrice !== (int) $referencePrice)
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="pm-this-{{ $i }}" value="this_only" wire:model="items.{{ $i }}.price_mode">
                                    <label class="form-check-label" for="pm-this-{{ $i }}">Hanya nota ini</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" id="pm-new-{{ $i }}" value="new_price" wire:model="items.{{ $i }}.price_mode">
                                    <label class="form-check-label" for="pm-new-{{ $i }}">Jadikan harga baru</label>
                                </div>
                                @if ($customerMode !== 'none')
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" id="pm-customer-{{ $i }}" value="customer_price" wire:model="items.{{ $i }}.price_mode">
                                        <label class="form-check-label" for="pm-customer-{{ $i }}">Jadikan harga pelanggan</label>
                                    </div>
                                @endif
                                @error("items.$i.price_mode") <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center">
                            <span>Subtotal: <strong>{{ \App\Support\Format::rupiah($this->rowSubtotal($row)) }}</strong></span>
                            <button type="button" class="btn btn-outline-danger btn-sm" wire:click="removeRow({{ $i }})">Hapus baris</button>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <button type="button" class="btn btn-outline-primary mb-2" wire:click="addRow">+ Tambah barang</button>
        @error('items') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

        <div class="card mb-3 mt-2">
            <div class="card-body">
                <h2 class="h6">Catatan</h2>
                <textarea class="form-control" rows="2" aria-label="Catatan" wire:model="note"></textarea>
            </div>
        </div>

        <div class="sticky-actions bg-white border-top p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span>Total</span>
                <strong>{{ \App\Support\Format::rupiah($this->total()) }}</strong>
            </div>
            <div class="d-flex gap-2">
                @if ($originalStatus === 'confirmed')
                    <button type="submit" class="btn btn-primary flex-fill" wire:loading.attr="disabled">Simpan perubahan</button>
                @else
                    <button type="button" class="btn btn-outline-secondary flex-fill" wire:click="saveDraft" wire:loading.attr="disabled">Simpan draf</button>
                    <button type="submit" class="btn btn-primary flex-fill" wire:loading.attr="disabled">Simpan transaksi</button>
                @endif
            </div>
            @if ($originalStatus !== 'confirmed')
                <button type="button" class="btn btn-outline-primary w-100 mt-2" wire:click="saveAndNew" wire:loading.attr="disabled">Simpan &amp; transaksi baru</button>
            @endif
        </div>
    </form>
</div>
