# Tugas 23: Hasil Cari Barang Dikelompokkan per Satuan

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 22 selesai.

## Tujuan

Di form transaksi, hasil cari barang sekarang berupa daftar maksimal 8 barang, urut nama. Barang dengan nama mirip tetapi satuan berbeda (GULA kg, GULA pcs) bercampur, sehingga mudah salah pilih.

Setelah tugas ini:

| Hal | Aturan |
|---|---|
| Pengelompokan | Hasil dikelompokkan per satuan. Setiap kelompok diberi judul nama satuan |
| Urutan kelompok | Mengikuti urutan daftar `config('shop.units')` (pcs, dus, pak, lusin, kg, ...) |
| Urutan di dalam kelompok | Nama A-Z |
| Jumlah hasil | Maksimal 20 barang. Yang diambil adalah 20 barang pertama yang cocok menurut nama A-Z, lalu dikelompokkan |
| Isi tiap baris | Nama barang di kiri, harga di kanan. Satuan tidak diulang di baris, karena sudah ada di judul kelompok |
| Berlaku di | Hasil cari barang, dan kotak "Barang yang mirip" saat membuat barang baru |

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 07, `searchProducts` | `orderBy('name')->limit(8)` | `orderBy('name')->limit(20)`, lalu diurutkan ulang per satuan |
| 07, daftar hasil di view | Dua salinan `@foreach` (hasil cari dan barang yang mirip) | Satu partial `livewire/_product-results.blade.php` yang dipakai di dua tempat |

Bentuk data `results` tidak berubah (`id`, `name`, `unit`, `price`), jadi `selectProduct` dan kode lain tidak berubah. Tidak ada rute, kolom, tabel, paket, atau JavaScript baru.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `resources/views/livewire/_product-results.blade.php`
- `tests/e2e/cari-barang-per-satuan.spec.js`

## Yang diubah

- `app/Livewire/TransactionForm.php` (hanya `searchProducts`)
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Feature/TransactionFormTest.php` (menambah dua tes)

## Langkah

### 1. searchProducts

Di `app/Livewire/TransactionForm.php`, ganti method `searchProducts` dengan:

```php
/**
 * Maksimal 20 barang yang namanya cocok, dikelompokkan per satuan
 * (urutan satuan mengikuti config('shop.units')), lalu urut nama di dalam kelompok.
 */
private function searchProducts(string $term): array
{
    $unitOrder = array_flip(config('shop.units'));

    return Product::selectable()
        ->with('currentPrice')
        ->where('name', 'like', '%'.$term.'%')
        ->orderBy('name')
        ->limit(20)
        ->get()
        ->sortBy([
            fn (Product $a, Product $b) => ($unitOrder[$a->unit] ?? PHP_INT_MAX) <=> ($unitOrder[$b->unit] ?? PHP_INT_MAX),
            fn (Product $a, Product $b) => strcmp($a->name, $b->name),
        ])
        ->values()
        ->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'unit' => $p->unit,
            'price' => $p->currentPrice?->sell_price,
        ])
        ->all();
}
```

Penjelasan:

- `limit(20)` diterapkan SEBELUM pengelompokan, pada urutan nama. Jadi pencarian yang cocok dengan banyak barang tetap menampilkan barang yang namanya paling awal.
- `sortBy` dengan dua fungsi pembanding mengurutkan per satuan lalu per nama. Satuan yang tidak ada di config diletakkan paling akhir.
- `values()` wajib, agar indeks array kembali 0, 1, 2, ... untuk Livewire.

### 2. Partial hasil cari

Buat `resources/views/livewire/_product-results.blade.php` dengan isi persis ini:

```blade
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
```

Penjelasan:

- Judul kelompok adalah `div`, bukan `button`, jadi tidak bisa diklik.
- Setiap judul dan baris punya `wire:key` unik, sesuai aturan Livewire di `00-aturan-umum.md`.

### 3. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, ada dua blok daftar hasil yang memakai `@foreach ($row['results'] as $r)`. Ganti keduanya dengan partial.

Hasil cari barang. Ganti `<div class="list-group mt-2"> ... </div>` di dalam `@if (count($row['results']) > 0)` dengan:

```blade
@include('livewire._product-results', ['results' => $row['results'], 'i' => $i, 'keyPrefix' => 'res-'.$row['key']])
```

Barang yang mirip. Ganti `<div class="list-group mt-2"> ... </div>` di bawah `<strong>Barang yang mirip:</strong>` dengan:

```blade
@include('livewire._product-results', ['results' => $row['results'], 'i' => $i, 'keyPrefix' => 'sim-'.$row['key']])
```

Awalan `res-` dan `sim-` sama dengan `wire:key` lama, jadi kunci tidak bentrok.

## Tes yang wajib ada

Tambahkan dua tes ini di akhir kelas `tests/Feature/TransactionFormTest.php`:

```php
    public function test_search_results_are_grouped_by_unit_then_sorted_by_name(): void
    {
        foreach ([['GULA PASIR', 'kg'], ['GULA MERAH', 'kg'], ['GULA SACHET', 'pcs'], ['GULA BATU', 'pak']] as [$name, $unit]) {
            $product = Product::factory()->create(['name' => $name, 'unit' => $unit]);
            ProductPrice::factory()->for($product)->create(['sell_price' => 1000]);
        }

        $component = Livewire::test(TransactionForm::class)->set('items.0.search', 'gula');

        $results = $component->get('items.0.results');
        $this->assertSame(['GULA SACHET', 'GULA BATU', 'GULA MERAH', 'GULA PASIR'], array_column($results, 'name'));
        $this->assertSame(['pcs', 'pak', 'kg', 'kg'], array_column($results, 'unit'));

        $component->assertSeeInOrder(['pcs', 'GULA SACHET', 'pak', 'GULA BATU', 'kg', 'GULA MERAH', 'GULA PASIR']);
    }

    public function test_search_returns_at_most_twenty_results(): void
    {
        foreach (range(1, 25) as $n) {
            Product::factory()->create(['name' => sprintf('KOPI %02d', $n), 'unit' => 'pcs']);
        }

        $results = Livewire::test(TransactionForm::class)->set('items.0.search', 'kopi')->get('items.0.results');

        $this->assertCount(20, $results);
        $this->assertSame('KOPI 01', $results[0]['name']);
        $this->assertSame('KOPI 20', $results[19]['name']);
    }
```

Buat `tests/e2e/cari-barang-per-satuan.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

async function newProduct(page, name, unit, price) {
    await page.goto('/products/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Satuan').selectOption(unit);
    await page.getByLabel('Harga jual').fill(price);
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/products$/);
}

test('form transaksi: hasil cari barang dikelompokkan per satuan lalu urut nama', async ({ page }) => {
    const suffix = Date.now();
    await newProduct(page, `Grup Pasir ${suffix}`, 'kg', '18000');
    await newProduct(page, `Grup Merah ${suffix}`, 'kg', '20000');
    await newProduct(page, `Grup Sachet ${suffix}`, 'pcs', '1000');

    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(String(suffix));

    const list = page.locator('.list-group').first();
    await expect(list.getByRole('button', { name: new RegExp(`GRUP SACHET ${suffix}`) })).toBeVisible();
    const lines = (await list.locator('.list-group-item').allInnerTexts()).map((t) => t.split('\n')[0].trim());
    expect(lines).toEqual(['pcs', `GRUP SACHET ${suffix}`, 'kg', `GRUP MERAH ${suffix}`, `GRUP PASIR ${suffix}`]);

    await list.getByRole('button', { name: new RegExp(`GRUP MERAH ${suffix}`) }).click();
    await expect(page.getByLabel('Harga satuan')).toHaveValue('20.000');
});
```

Tes lama tidak perlu diubah dan harus tetap lulus. Tes lama mencari tombol hasil dengan pola nama barang, dan pola itu tetap cocok.

## Kriteria selesai

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 2.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: mengetik nama barang di form transaksi menampilkan hasil dengan judul satuan (pcs, pak, kg, ...) dan nama urut A-Z di setiap kelompok.
- [ ] Kotak "Barang yang mirip" juga dikelompokkan.
- [ ] Judul kelompok tidak bisa diklik. Memilih barang tetap mengisi harga seperti sebelumnya.
- [ ] Form rapi di lebar 375px.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah bentuk data `results`.
- Jangan memakai `<select>` atau `<optgroup>`. Daftar tetap berupa tombol seperti sekarang.
- Jangan menulis SQL khusus untuk mengurutkan per satuan. Pengurutan dilakukan di PHP.
- Jangan menghapus atau melemahkan tes agar lulus.
