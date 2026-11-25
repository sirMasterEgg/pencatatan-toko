# Tugas 24: Nama dan Alamat Pelanggan Huruf Besar, Tampil "NAMA - ALAMAT"

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 23 selesai.

## Tujuan

Sama seperti nama barang di tugas 14, nama dan alamat pelanggan disimpan dalam huruf besar. Pelanggan dengan nama sama (misalnya dua "BU SARI") dibedakan lewat alamat, jadi di semua tempat pelanggan ditampilkan sebagai `NAMA - ALAMAT`.

| Hal | Aturan |
|---|---|
| Nama | Disimpan huruf besar dan di-`trim`. Contoh: `  bu sari ` menjadi `BU SARI` |
| Alamat | Disimpan huruf besar dan di-`trim`. Alamat kosong atau hanya spasi disimpan `null` |
| Asal data | Halaman tambah dan ubah pelanggan, dan pelanggan baru dari form transaksi |
| Data lama | Diubah sekali lewat migrasi baru |
| Tampilan | `NAMA - ALAMAT`, atau `NAMA` saja jika alamat kosong |
| Tempat tampilan | Daftar pelanggan, judul halaman detail pelanggan, hasil cari dan pelanggan terpilih di form transaksi, daftar transaksi, detail transaksi, beranda, laporan harian |
| Nomor HP | Tetap tampil sebagai teks tambahan di tempat yang sekarang menampilkannya |

Nomor HP dan catatan pelanggan tidak diubah hurufnya. Pencarian pelanggan tetap tidak membedakan huruf besar kecil.

## Hubungan dengan tugas lama

| Sumber | Aturan lama | Aturan baru |
|---|---|---|
| 05, `CustomerController::validated` | Mengembalikan hasil validasi apa adanya | Nama dan alamat dirapikan dengan `CustomerService::normalizeText` |
| 05, `CustomerService::findOrCreate` | Simpan nama yang di-`trim` dan alamat apa adanya | Nama dan alamat lewat `normalizeText` |
| 05, 07, 08, 09, view | Menampilkan `$customer->name` | Menampilkan `$customer->displayName()` |
| 00, larangan mengubah migrasi lama | Berlaku | Tetap berlaku. Tugas ini menambah migrasi BARU |

Tidak ada rute, kolom, tabel, paket, atau JavaScript baru.

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `database/migrations/2026_11_25_000001_uppercase_customer_names.php`
- `tests/Feature/CustomerNameTest.php`
- `tests/e2e/pelanggan-huruf-besar.spec.js`

## Yang diubah

- `app/Services/CustomerService.php`
- `app/Http/Controllers/CustomerController.php` (hanya method privat `validated`)
- `app/Models/Customer.php`
- `app/Livewire/TransactionForm.php` (`searchCustomers` dan `selectCustomer`)
- View: `customers/_form`, `customers/index`, `customers/show`, `livewire/transaction-form`, `transactions/index`, `transactions/show`, `dashboard`, `reports/daily`
- Lima file tes lama, lihat langkah 6
- `tests/e2e/harga-pelanggan.spec.js` (satu baris, lihat langkah 6)

## Langkah

### 1. CustomerService

Tambahkan method statis ini sebagai method PERTAMA di kelas `CustomerService`:

```php
/**
 * Nama dan alamat pelanggan disimpan huruf besar. Teks kosong menjadi null.
 */
public static function normalizeText(?string $value): ?string
{
    $value = trim((string) $value);

    return $value === '' ? null : mb_strtoupper($value);
}
```

Di `findOrCreate`, ubah bagian `Customer::create([...])` menjadi:

```php
return Customer::create([
    'name' => self::normalizeText($name),
    'phone' => $normalized,
    'address' => self::normalizeText($address),
]);
```

Pencocokan lewat nomor HP di `findOrCreate` tidak berubah. Pelanggan lama yang ditemukan lewat nomor HP tidak diubah namanya.

### 2. CustomerController

Tambahkan `use App\Services\CustomerService;` di bagian atas jika belum ada. Ubah method privat `validated` agar hasil validasi disimpan ke variabel, lalu nama dan alamat dirapikan:

```php
private function validated(Request $request, ?Customer $customer = null): array
{
    $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer?->id)],
        'address' => ['nullable', 'string', 'max:255'],
        'note' => ['nullable', 'string', 'max:255'],
    ], [
        'phone.unique' => 'Nomor HP ini sudah dipakai pelanggan lain.',
    ]);

    $data['name'] = CustomerService::normalizeText($data['name']);
    $data['address'] = CustomerService::normalizeText($data['address'] ?? null);

    return $data;
}
```

### 3. Model Customer

Tambahkan method ini tepat di atas `transactions()`:

```php
/**
 * Tampilan pelanggan di layar: "NAMA - ALAMAT", atau "NAMA" jika alamat kosong.
 */
public function displayName(): string
{
    return $this->address ? $this->name.' - '.$this->address : $this->name;
}
```

Ini satu-satunya tempat format `NAMA - ALAMAT`. Jangan menyusun format itu di view atau di tempat lain.

### 4. Migrasi data

Buat `database/migrations/2026_11_25_000001_uppercase_customer_names.php` dengan isi persis ini, lalu jalankan `php artisan migrate`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tugas 24: nama dan alamat pelanggan selalu huruf besar. Ubah data lama sekali saja.
     */
    public function up(): void
    {
        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                $name = mb_strtoupper(trim($customer->name));
                $address = $customer->address === null || trim($customer->address) === ''
                    ? null
                    : mb_strtoupper(trim($customer->address));

                if ($name !== $customer->name || $address !== $customer->address) {
                    DB::table('customers')->where('id', $customer->id)->update(['name' => $name, 'address' => $address]);
                }
            }
        });
    }

    /**
     * Nama asli tidak disimpan, jadi tidak bisa dikembalikan.
     */
    public function down(): void
    {
        //
    }
};
```

Pakai `mb_strtoupper` di PHP, bukan `UPPER()` di SQL, sama seperti migrasi nama barang di tugas 14.

### 5. Tampilan

Form pelanggan (`resources/views/customers/_form.blade.php`). Tambahkan kelas `text-uppercase` dan atribut `autocapitalize="characters"` ke kolom Nama dan Alamat:

```blade
<input type="text" id="name" name="name" value="{{ old('name', $customer->name ?? '') }}" class="form-control text-uppercase @error('name') is-invalid @enderror" autocapitalize="characters" required>
```

```blade
<input type="text" id="address" name="address" value="{{ old('address', $customer->address ?? '') }}" class="form-control text-uppercase @error('address') is-invalid @enderror" autocapitalize="characters">
```

Form transaksi (`resources/views/livewire/transaction-form.blade.php`), kolom nama pelanggan baru:

```blade
<input type="text" id="customerName" class="form-control text-uppercase @error('customer.name') is-invalid @enderror" autocapitalize="characters" wire:model="customerName">
```

Ganti teks nama pelanggan dengan `displayName()` di tempat-tempat berikut:

| File | Dari | Menjadi |
|---|---|---|
| `customers/index.blade.php` | `{{ $customer->name }}` | `{{ $customer->displayName() }}` |
| `customers/show.blade.php` | `@section('title', $customer->name)` | `@section('title', $customer->displayName())` |
| `customers/show.blade.php` | `<h1 class="h4 mb-0">{{ $customer->name }}</h1>` | `<h1 class="h4 mb-0">{{ $customer->displayName() }}</h1>` |
| `transactions/index.blade.php` | `{{ $transaction->customer?->name ?? 'Pembeli umum' }}` | `{{ $transaction->customer?->displayName() ?? 'Pembeli umum' }}` |
| `transactions/show.blade.php` | `{{ $transaction->customer->name }}</a>` | `{{ $transaction->customer->displayName() }}</a>` |
| `dashboard.blade.php` | `{{ $transaction->customer?->name ?? 'Pembeli umum' }}` | `{{ $transaction->customer?->displayName() ?? 'Pembeli umum' }}` |
| `reports/daily.blade.php` | `{{ $transaction->customer?->name ?? 'Pembeli umum' }}` | `{{ $transaction->customer?->displayName() ?? 'Pembeli umum' }}` |
| `livewire/transaction-form.blade.php` | `{{ $c['name'] }}@if ($c['phone'])` | `{{ $c['label'] }}@if ($c['phone'])` |

Di `app/Livewire/TransactionForm.php`:

- Di `searchCustomers`, ubah `map` menjadi:

  ```php
  ->map(fn (Customer $c) => ['id' => $c->id, 'label' => $c->displayName(), 'phone' => $c->phone])
  ```

- Di `selectCustomer`, ubah baris `customerLabel` menjadi:

  ```php
  $this->customerLabel = $customer->phone ? $customer->displayName().' ('.$customer->phone.')' : $customer->displayName();
  ```

Kolom `value` di form ubah pelanggan tetap memakai `$customer->name` dan `$customer->address`, bukan `displayName()`.

### 6. Sesuaikan tes lama

Tes berikut membuat pelanggan lewat `POST /customers`, `PUT /customers/{id}`, atau `TransactionService`, lalu memeriksa nama dengan huruf kecil. Ubah HANYA teks nama pada baris berikut:

| File | Baris yang berisi | Ubah menjadi |
|---|---|---|
| `tests/Feature/CustomerTest.php` | `$this->assertSame('Nama Baru', $customer->fresh()->name);` | `$this->assertSame('NAMA BARU', $customer->fresh()->name);` |
| `tests/Feature/CustomerTest.php` | `$this->assertSame('Bu Sari', $found->fresh()->name);` | `$this->assertSame('BU SARI', $found->fresh()->name);` |
| `tests/Feature/FullFlowTest.php` | `->assertSee('Bu Sari')` di bagian detail transaksi | `->assertSee('BU SARI')` |
| `tests/Feature/TransactionPageTest.php` | `->assertSee('Bu Sari');` | `->assertSee('BU SARI');` |
| `tests/Feature/CustomerPriceTransactionTest.php` | `Customer::where('name', 'Pak Budi')` | `Customer::where('name', 'PAK BUDI')` |

Catatan untuk baris `'Bu Sari'` di `CustomerTest`: pelanggan itu dibuat lewat `findOrCreate`, jadi namanya sekarang tersimpan huruf besar. Pemanggilan kedua menemukannya lewat nomor HP dan tidak mengubah namanya. Itu yang diperiksa tes.

Di `tests/e2e/harga-pelanggan.spec.js`, ubah:

```js
    await page.getByRole('button', { name: new RegExp(customer) }).click();
```

menjadi:

```js
    await page.getByRole('button', { name: new RegExp(customer, 'i') }).click();
```

Pola `new RegExp(...)` membedakan huruf besar kecil, dan nama pelanggan sekarang huruf besar.

Ini satu-satunya perubahan tes lama yang diizinkan di tugas ini. Jika masih ada tes gagal karena huruf besar di baris lain, berhenti dan laporkan.

## Tes yang wajib ada

Buat `tests/Feature/CustomerNameTest.php` dengan isi persis ini:

```php
<?php

namespace Tests\Feature;

use App\Livewire\TransactionForm;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_normalize_text(): void
    {
        $this->assertSame('BU SARI', CustomerService::normalizeText('  bu sari '));
        $this->assertNull(CustomerService::normalizeText('   '));
        $this->assertNull(CustomerService::normalizeText(null));
    }

    public function test_store_saves_name_and_address_in_uppercase(): void
    {
        $this->post('/customers', ['name' => '  bu sari ', 'address' => 'jl. mawar 1'])->assertSessionDoesntHaveErrors();

        $customer = Customer::firstOrFail();
        $this->assertSame('BU SARI', $customer->name);
        $this->assertSame('JL. MAWAR 1', $customer->address);
    }

    public function test_empty_address_is_saved_as_null(): void
    {
        $this->post('/customers', ['name' => 'Pak Budi', 'address' => '  ']);

        $this->assertNull(Customer::firstOrFail()->address);
    }

    public function test_update_saves_name_and_address_in_uppercase(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => null]);

        $this->put("/customers/{$customer->id}", ['name' => 'Bu Sari Rahayu', 'address' => 'Jl. Melati 2'])->assertSessionDoesntHaveErrors();

        $this->assertSame('BU SARI RAHAYU', $customer->fresh()->name);
        $this->assertSame('JL. MELATI 2', $customer->fresh()->address);
    }

    public function test_customer_from_transaction_form_is_saved_in_uppercase(): void
    {
        app(CustomerService::class)->findOrCreate('pak budi', null, 'gang kenari');

        $customer = Customer::firstOrFail();
        $this->assertSame('PAK BUDI', $customer->name);
        $this->assertSame('GANG KENARI', $customer->address);
    }

    public function test_display_name(): void
    {
        $this->assertSame('BU SARI - JL. MAWAR 1', (new Customer(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']))->displayName());
        $this->assertSame('PAK BUDI', (new Customer(['name' => 'PAK BUDI', 'address' => null]))->displayName());
    }

    public function test_customer_pages_show_display_name_and_search_ignores_case(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']);

        $this->get('/customers?q=sari')->assertOk()->assertSee('BU SARI - JL. MAWAR 1');
        $this->get("/customers/{$customer->id}")->assertOk()->assertSee('<h1 class="h4 mb-0">BU SARI - JL. MAWAR 1</h1>', false);
    }

    public function test_transaction_pages_show_display_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1']);
        $product = Product::factory()->create(['unit' => 'kg']);
        ProductPrice::factory()->for($product)->create(['sell_price' => 1000]);

        $transaction = app(TransactionService::class)->create([
            'transacted_at' => now()->subMinute()->format('Y-m-d H:i'),
            'channel' => 'store',
            'payment_status' => 'paid',
            'customer' => ['id' => $customer->id],
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000, 'discount' => 0]],
        ]);

        foreach (['/transactions', "/transactions/{$transaction->id}", '/', '/reports/daily'] as $url) {
            $this->get($url)->assertOk()->assertSee('BU SARI - JL. MAWAR 1');
        }
    }

    public function test_transaction_form_uses_display_name(): void
    {
        $customer = Customer::factory()->create(['name' => 'BU SARI', 'address' => 'JL. MAWAR 1', 'phone' => '6281200001234']);

        Livewire::test(TransactionForm::class)
            ->set('customerSearch', 'sari')
            ->assertSet('customerResults.0.label', 'BU SARI - JL. MAWAR 1')
            ->assertSee('BU SARI - JL. MAWAR 1')
            ->call('selectCustomer', $customer->id)
            ->assertSet('customerLabel', 'BU SARI - JL. MAWAR 1 (6281200001234)');
    }

    public function test_name_and_address_inputs_are_shown_in_uppercase(): void
    {
        $this->get('/customers/create')
            ->assertOk()
            ->assertSee('id="name" name="name" value="" class="form-control text-uppercase', false)
            ->assertSee('id="address" name="address" value="" class="form-control text-uppercase', false);
    }

    public function test_migration_uppercases_existing_names_and_addresses(): void
    {
        $customer = Customer::factory()->create(['name' => 'bu sari', 'address' => 'jl. mawar 1']);
        $noAddress = Customer::factory()->create(['name' => 'pak budi', 'address' => '']);

        $migration = require database_path('migrations/2026_11_25_000001_uppercase_customer_names.php');
        $migration->up();

        $this->assertSame('BU SARI', $customer->fresh()->name);
        $this->assertSame('JL. MAWAR 1', $customer->fresh()->address);
        $this->assertSame('PAK BUDI', $noAddress->fresh()->name);
        $this->assertNull($noAddress->fresh()->address);
    }
}
```

Buat `tests/e2e/pelanggan-huruf-besar.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

test('pelanggan: nama dan alamat huruf besar, tampil "NAMA - ALAMAT" di form transaksi', async ({ page }) => {
    const suffix = Date.now();
    const name = `Bu Rahma ${suffix}`;

    await page.goto('/customers/create');
    await page.getByLabel('Nama').fill(name);
    await page.getByLabel('Alamat').fill('jl. kenanga 5');
    await page.getByRole('button', { name: 'Simpan', exact: true }).click();
    await page.waitForURL(/\/customers$/);

    await page.goto(`/customers?q=${suffix}`);
    await expect(page.getByText(`BU RAHMA ${suffix} - JL. KENANGA 5`)).toBeVisible();

    await page.goto('/transactions/create');
    await page.getByPlaceholder(/Cari nama atau nomor HP/).fill(String(suffix));
    await page.getByRole('button', { name: `BU RAHMA ${suffix} - JL. KENANGA 5` }).click();
    await expect(page.getByText(`BU RAHMA ${suffix} - JL. KENANGA 5`)).toBeVisible();

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
});
```

## Kriteria selesai

- [ ] `php artisan migrate` berhasil, dan nama serta alamat pelanggan lama menjadi huruf besar.
- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 11.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 1.
- [ ] Di browser HP: mengetik nama dan alamat pelanggan langsung tampil huruf besar.
- [ ] Pelanggan tampil `NAMA - ALAMAT` di daftar pelanggan, form transaksi, daftar dan detail transaksi, beranda, dan laporan harian.
- [ ] Pelanggan tanpa alamat tampil `NAMA` saja, tanpa tanda `-`.
- [ ] Halaman rapi di lebar 375px.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan mengubah migrasi lama atau struktur tabel.
- Jangan memakai mutator, accessor `name`, atau event model untuk mengubah huruf. Satu-satunya tempat adalah `CustomerService::normalizeText`.
- Jangan mengubah huruf nomor HP atau catatan.
- Jangan menyusun `NAMA - ALAMAT` di luar `Customer::displayName()`.
- Jangan mengubah tes lama selain yang tertulis di langkah 6.
- Jangan menghapus atau melemahkan tes agar lulus.
