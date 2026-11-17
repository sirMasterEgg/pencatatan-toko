# Tugas 16: Form Transaksi untuk Draf dan Ubah

Baca dulu: `00-aturan-umum.md` dan `15-draf-dan-ubah-transaksi.md` (bagian "Tujuan", "Hubungan dengan tugas lama", dan "Keputusan desain")
Prasyarat: tugas 01 sampai 15 selesai.

## Tujuan

Tugas 15 sudah membuat layanan (`create` dengan status, `update`, `deleteDraft`), rute `transactions.edit` dan `transactions.destroy`, serta halaman detail dengan tombol "Ubah transaksi" dan "Hapus draf". Tugas ini menyambungkan form transaksi Livewire ke semua itu.

Setelah tugas ini, tombol di bagian bawah form transaksi:

| Halaman | Tombol | Hasil |
|---|---|---|
| Transaksi baru (`/transactions/create`) | "Simpan draf" | Disimpan sebagai `draft` |
| | "Simpan transaksi" | Disimpan sebagai `confirmed`, seperti sebelumnya |
| Ubah draf (`/transactions/{id}/edit`) | "Simpan draf" | Tetap `draft`, isi diperbarui |
| | "Simpan transaksi" | Menjadi `confirmed` |
| Ubah transaksi `confirmed` yang belum bayar | "Simpan perubahan" | Tetap `confirmed`, isi diperbarui. Tidak ada tombol draf |

Di halaman ubah, form terisi data transaksi: tanggal, saluran, status bayar, catatan, pelanggan, dan semua barang dengan jumlah, harga, dan diskonnya.

Pesan sukses:

| Keadaan | Pesan |
|---|---|
| Disimpan sebagai draf | `Draf TRX-... berhasil disimpan.` |
| Transaksi `confirmed` diubah | `Transaksi TRX-... berhasil diperbarui.` |
| Lainnya (transaksi baru, atau draf dijadikan transaksi) | `Transaksi TRX-... berhasil disimpan.` |

## Hubungan dengan tugas lama

| Tugas lama | Aturan lama | Aturan baru |
|---|---|---|
| 07, `TransactionForm::mount()` | Tanpa parameter | `mount(?Transaction $transaction = null)`. Jika ada transaksi, form diisi dari transaksi itu |
| 07, `save` | Satu-satunya aksi simpan, selalu `create` | `save` dan `saveDraft` memanggil method privat `persist`, yang memanggil `create` atau `update` |
| 07, bagian bawah form | Total dan satu tombol "Simpan transaksi" dalam satu baris | Total di baris pertama, tombol di baris kedua |
| 07, "Komponen tidak menyimpan data sendiri" | Berlaku | Tetap berlaku. Komponen hanya memanggil `TransactionService` |

Tes e2e dijalankan seperti di tugas 11. Lihat bagian "Tentang tes e2e" di `11-alur-tambah-barang-dan-pelanggan.md`.

## Yang dibuat

- `tests/e2e/draf-dan-ubah.spec.js`

## Yang diubah

- `app/Support/Format.php`
- `app/Livewire/TransactionForm.php`
- `resources/views/livewire/transaction-form.blade.php`
- `tests/Unit/FormatTest.php`, `tests/Feature/TransactionFormTest.php` (menambah tes)
- `README.md` di akar proyek

## Langkah

### 1. Format::quantityInput

Jumlah yang diisikan kembali ke kolom form tidak boleh memakai titik ribuan. `Format::quantity(1000)` menghasilkan `1.000`, dan `Parse::quantity('1.000')` membacanya sebagai 1. Karena itu dibuat method khusus.

Di `app/Support/Format.php`, tambahkan method ini tepat di atas `quantity`:

```php
/**
 * Jumlah untuk diisikan kembali ke kolom form: tanpa titik ribuan, koma sebagai desimal.
 * Contoh: 1000 menjadi "1000", "0.500" menjadi "0,5".
 */
public static function quantityInput(int|float|string|null $quantity): string
{
    $text = number_format((float) ($quantity ?? 0), 3, ',', '');

    return rtrim(rtrim($text, '0'), ',');
}
```

### 2. Komponen TransactionForm: use dan properti

Tambahkan `use` berikut di bagian atas, di samping yang sudah ada:

```php
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Livewire\Attributes\Locked;
```

Tambahkan dua properti ini sebagai properti PERTAMA di dalam kelas, di atas `$transactedAt`:

```php
#[Locked]
public ?int $transactionId = null;

#[Locked]
public ?string $originalStatus = null;
```

`#[Locked]` wajib. Tanpa itu, isi properti bisa diubah dari peramban, sehingga orang bisa menyimpan ke transaksi lain. Tes `test_transaction_id_cannot_be_changed_from_browser` memeriksanya.

### 3. mount dan fillFromTransaction

Ganti method `mount` lama dengan dua method ini:

```php
public function mount(?Transaction $transaction = null): void
{
    if ($transaction?->exists) {
        $this->fillFromTransaction($transaction);

        return;
    }

    $this->transactedAt = now()->format('Y-m-d\TH:i');
    $this->items = [$this->emptyRow()];
}

private function fillFromTransaction(Transaction $transaction): void
{
    $transaction->load('items.product.currentPrice', 'customer');

    $this->transactionId = $transaction->id;
    $this->originalStatus = $transaction->status->value;
    $this->transactedAt = $transaction->transacted_at->format('Y-m-d\TH:i');
    $this->channel = $transaction->channel->value;
    $this->paymentStatus = $transaction->payment_status->value;
    $this->note = $transaction->note ?? '';

    if ($transaction->customer) {
        $this->selectCustomer($transaction->customer->id);
    }

    $this->items = $transaction->items
        ->map(fn (TransactionItem $item) => array_merge($this->emptyRow(), [
            'mode' => 'existing',
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'unit' => $item->product->unit,
            'list_price' => $item->product->currentPrice?->sell_price,
            'quantity' => Format::quantityInput($item->quantity),
            'unit_price' => Format::thousands($item->unit_price),
            'discount' => Format::thousands($item->discount),
        ]))
        ->all();

    if (count($this->items) === 0) {
        $this->items = [$this->emptyRow()];
    }
}
```

Penjelasan:

- `$transaction?->exists` dipakai, bukan `$transaction !== null`, karena Livewire bisa memberi model kosong.
- Pelanggan dimuat lewat `selectCustomer` yang sudah ada, agar label pelanggan sama dengan saat dipilih manual.
- Setiap baris memakai `emptyRow()` sebagai dasar, agar `key`, `search`, `results`, `cost_price`, dan `price_mode` punya nilai awal yang benar.
- `list_price` diisi harga yang berlaku SEKARANG. Pilihan "Jadikan harga baru" muncul jika harga satuan berbeda dari harga itu.
- Jumlah memakai `Format::quantityInput`, harga dan diskon memakai `Format::thousands` dari tugas 13.

### 4. save, saveDraft, dan persist

Ubah method `save` lama menjadi `persist`, lalu tambahkan `save` dan `saveDraft` baru di atasnya:

```php
public function save(TransactionService $service)
{
    return $this->persist($service, TransactionStatus::Confirmed);
}

public function saveDraft(TransactionService $service)
{
    return $this->persist($service, TransactionStatus::Draft);
}

private function persist(TransactionService $service, TransactionStatus $status)
{
    $this->resetErrorBag();

    // ... isi lama method save, tidak berubah, sampai sebelum $transaction = $service->create([...]);
```

Isi `persist` sama dengan isi `save` lama, kecuali bagian akhirnya. Ganti bagian akhir, mulai dari `$transaction = $service->create([` sampai akhir method, dengan:

```php
    $data = [
        'transacted_at' => str_replace('T', ' ', $this->transactedAt),
        'channel' => $this->channel,
        'payment_status' => $this->paymentStatus,
        'note' => $this->note !== '' ? $this->note : null,
        'customer' => $customer,
        'items' => $items,
    ];

    if ($this->transactionId !== null) {
        $transaction = $service->update(Transaction::findOrFail($this->transactionId), $data, $status);
    } else {
        $transaction = $service->create($data, $status);
    }

    $message = match (true) {
        $status === TransactionStatus::Draft => 'Draf '.$transaction->number.' berhasil disimpan.',
        $this->originalStatus === TransactionStatus::Confirmed->value => 'Transaksi '.$transaction->number.' berhasil diperbarui.',
        default => 'Transaksi '.$transaction->number.' berhasil disimpan.',
    };

    session()->flash('success', $message);

    return $this->redirect(route('transactions.show', $transaction));
}
```

Aturan:

- `save` tetap menjadi aksi `wire:submit` form. Menekan Enter di kolom teks tetap berarti "Simpan transaksi" atau "Simpan perubahan".
- Jangan memeriksa `isEditable()` di komponen. `TransactionService::update` sudah menolaknya dengan galat `status`.
- Jangan menyimpan model `Transaction` sebagai properti publik. Simpan hanya `transactionId`.

### 5. View form transaksi

Di `resources/views/livewire/transaction-form.blade.php`, tepat di bawah `<form wire:submit="save">`, tambahkan tempat untuk galat `status` dari service:

```blade
        @error('status') <div class="alert alert-danger">{{ $message }}</div> @enderror

```

Lalu ganti seluruh blok `<div class="sticky-actions ...">` di bagian bawah form dengan:

```blade
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
        </div>
```

Aturan:

- Tombol "Simpan draf" `type="button"` dengan `wire:click="saveDraft"`. Jangan `type="submit"`.
- Tombol utama tetap `type="submit"`.
- Total dipindah ke baris sendiri agar dua tombol muat di lebar 375px.

### 6. README proyek

Tugas ini menutup rangkaian tugas 12 sampai 16. Perbarui `README.md` di akar proyek:

- Pada baris `Status`, ganti `Tugas 01 sampai 11` menjadi `Tugas 01 sampai 16`.
- Di bagian "Fitur tahap 1", tambahkan empat butir ini di paling bawah:

  ```
  - **Jumlah desimal**: kg, gram, liter, dan meter boleh desimal (contoh `0,5`). Koma dan titik sama-sama tanda desimal. Satuan lain wajib bilangan bulat
  - **Titik ribuan**: kolom harga, harga modal, dan diskon langsung diberi titik saat diketik (`15000` menjadi `15.000`)
  - **Nama barang huruf besar**: nama barang selalu disimpan dalam huruf besar
  - **Draf dan ubah transaksi**: transaksi bisa disimpan sebagai draf (tidak masuk laporan, bisa dihapus). Transaksi yang belum dibayar bisa diubah. Setelah dibayar sebagian atau lunas, isinya terkunci
  ```

- Di bagian "Belum ada", ganti butir `Ubah atau hapus transaksi, grafik` menjadi `Hapus transaksi yang sudah dikonfirmasi, grafik`.
- Di bagian "Rute", baris Transaksi, tambahkan `GET /transactions/{id}/edit` dan `DELETE /transactions/{id}` (hanya draf).

Bagian lain README tidak diubah.

## Tes yang wajib ada

`tests/Unit/FormatTest.php`, tambah `test_quantity_input`:

| Masukan | Hasil |
|---|---|
| `1000` | `'1000'` |
| `'0.500'` | `'0,5'` |
| `'2.000'` | `'2'` |
| `1.25` | `'1,25'` |

`tests/Feature/TransactionFormTest.php`: tambahkan `use` berikut di bagian atas:

```php
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Services\TransactionService;
```

Lalu tambahkan helper dan tujuh tes ini di akhir kelas:

```php
    private function unpaidTransaction(array $extra = []): Transaction
    {
        $gula = Product::factory()->create(['name' => 'GULA', 'unit' => 'kg']);
        ProductPrice::factory()->for($gula)->create(['sell_price' => 18000, 'effective_from' => now()->subDay()]);

        return app(TransactionService::class)->create(array_merge([
            'transacted_at' => now()->subHour()->format('Y-m-d H:i'),
            'channel' => 'whatsapp',
            'payment_status' => 'unpaid',
            'note' => 'Antar sore',
            'customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234'],
            'items' => [['product_id' => $gula->id, 'quantity' => '1,5', 'unit_price' => 18000, 'discount' => 1000, 'price_mode' => 'this_only']],
        ], $extra));
    }

    public function test_save_draft_creates_draft_and_redirects(): void
    {
        $product = Product::factory()->create(['name' => 'MI INSTAN', 'unit' => 'dus']);
        ProductPrice::factory()->for($product)->create(['sell_price' => 118000]);

        $component = Livewire::test(TransactionForm::class)
            ->call('selectProduct', 0, $product->id)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(TransactionStatus::Draft, $transaction->status);
        $component->assertRedirect('/transactions/'.$transaction->id);
        $this->assertSame('Draf '.$transaction->number.' berhasil disimpan.', session('success'));
    }

    public function test_edit_mode_loads_transaction_into_form(): void
    {
        $transaction = $this->unpaidTransaction();

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->assertSet('transactionId', $transaction->id)
            ->assertSet('originalStatus', 'confirmed')
            ->assertSet('channel', 'whatsapp')
            ->assertSet('paymentStatus', 'unpaid')
            ->assertSet('note', 'Antar sore')
            ->assertSet('customerMode', 'existing')
            ->assertSet('customerId', Customer::firstOrFail()->id)
            ->assertSet('items.0.mode', 'existing')
            ->assertSet('items.0.name', 'GULA')
            ->assertSet('items.0.quantity', '1,5')
            ->assertSet('items.0.unit_price', '18.000')
            ->assertSet('items.0.discount', '1.000')
            ->assertSet('items.0.list_price', 18000)
            ->assertSee('Simpan perubahan')
            ->assertDontSee('Simpan draf');
    }

    public function test_edit_mode_save_updates_same_transaction(): void
    {
        $transaction = $this->unpaidTransaction();

        $component = Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->set('items.0.quantity', '2')
            ->set('items.0.discount', '0')
            ->set('paymentStatus', 'paid')
            ->call('save')
            ->assertHasNoErrors();

        $component->assertRedirect('/transactions/'.$transaction->id);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(36000, $transaction->fresh()->total);
        $this->assertFalse($transaction->fresh()->isEditable());
        $this->assertSame('Transaksi '.$transaction->number.' berhasil diperbarui.', session('success'));
    }

    public function test_edit_mode_of_confirmed_transaction_cannot_save_as_draft(): void
    {
        $transaction = $this->unpaidTransaction();

        Livewire::test(TransactionForm::class, ['transaction' => $transaction])
            ->call('saveDraft')
            ->assertHasErrors('status')
            ->assertSee('Transaksi yang sudah dikonfirmasi tidak bisa dijadikan draf.');

        $this->assertSame(TransactionStatus::Confirmed, $transaction->fresh()->status);
    }

    public function test_draft_edit_mode_shows_both_buttons_and_confirms(): void
    {
        $draft = $this->unpaidTransaction();
        $draft->update(['status' => TransactionStatus::Draft]);

        Livewire::test(TransactionForm::class, ['transaction' => $draft->fresh()])
            ->assertSee('Simpan draf')
            ->assertSee('Simpan transaksi')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(TransactionStatus::Confirmed, $draft->fresh()->status);
        $this->assertSame('Transaksi '.$draft->number.' berhasil disimpan.', session('success'));
    }

    public function test_transaction_paid_after_form_opened_is_rejected(): void
    {
        $transaction = $this->unpaidTransaction();

        $component = Livewire::test(TransactionForm::class, ['transaction' => $transaction]);
        $transaction->update(['payment_status' => 'paid']);

        $component->set('items.0.quantity', '5')
            ->call('save')
            ->assertHasErrors('status');

        $this->assertSame(26000, $transaction->fresh()->total);
    }

    public function test_transaction_id_cannot_be_changed_from_browser(): void
    {
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Livewire::test(TransactionForm::class)->set('transactionId', 1);
    }
```

Tes e2e: buat `tests/e2e/draf-dan-ubah.spec.js` dengan isi persis ini:

```js
import { test, expect } from '@playwright/test';

const noOverflow = async (page) => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
};

async function newDraft(page, name) {
    await page.goto('/transactions/create');
    await page.getByPlaceholder('Ketik nama barang').fill(name);
    await page.getByRole('button', { name: `+ Tambah "${name}" sebagai barang baru` }).click();
    await page.getByLabel('Satuan').selectOption('kg');
    await page.getByLabel('Harga jual').fill('10000');
    await page.getByLabel('Jumlah').fill('2');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp20.000').first()).toBeVisible();
    await noOverflow(page);

    await page.getByRole('button', { name: 'Simpan draf' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Draf TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByText('Ini draf.')).toBeVisible();
    return page.url();
}

test('draf: simpan, ubah, lalu jadikan transaksi; kunci setelah dibayar', async ({ page }) => {
    const draftUrl = await newDraft(page, `Draf ${Date.now()}`);

    await page.goto('/transactions?status=draft');
    await expect(page.locator('.badge', { hasText: 'Draf' }).first()).toBeVisible();

    await page.goto(draftUrl);
    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+\/edit$/);
    await expect(page.getByRole('heading', { name: /Ubah draf TRX-/ })).toBeVisible();
    await expect(page.getByLabel('Jumlah')).toHaveValue('2');
    await expect(page.getByLabel('Harga satuan')).toHaveValue('10.000');
    await noOverflow(page);

    await page.getByLabel('Jumlah').fill('2,5');
    await page.getByLabel('Diskon').click();
    await expect(page.getByText('Rp25.000').first()).toBeVisible();
    await page.getByLabel('Status bayar').selectOption('unpaid');
    await page.getByRole('button', { name: 'Simpan transaksi' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil disimpan\./)).toBeVisible();
    await expect(page.getByText('Ini draf.')).toHaveCount(0);
    await expect(page.getByText('2,5 kg')).toBeVisible();

    // Dikonfirmasi tetapi belum bayar: masih bisa diubah, tanpa tombol draf.
    await page.getByRole('link', { name: 'Ubah transaksi' }).click();
    await expect(page.getByRole('button', { name: 'Simpan draf' })).toHaveCount(0);
    await page.getByLabel('Jumlah').fill('3');
    await page.getByLabel('Diskon').click();
    await page.getByRole('button', { name: 'Simpan perubahan' }).click();
    await page.waitForURL(/\/transactions\/\d+$/);
    await expect(page.getByText(/Transaksi TRX-\d{8}-\d{3} berhasil diperbarui\./)).toBeVisible();
    await expect(page.getByText('Rp30.000').first()).toBeVisible();

    // Setelah dibayar: terkunci.
    await page.getByLabel('Status bayar', { exact: true }).selectOption('paid');
    await page.getByRole('button', { name: 'Simpan status bayar' }).click();
    await expect(page.getByText('Status bayar diperbarui.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ubah transaksi' })).toHaveCount(0);

    await page.goto(page.url() + '/edit');
    await expect(page.getByText('Transaksi ini tidak bisa diubah karena sudah dibayar atau dibatalkan.')).toBeVisible();
});

test('draf: hapus draf', async ({ page }) => {
    await newDraft(page, `Hapus ${Date.now()}`);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hapus draf' }).click();
    await page.waitForURL(/\/transactions$/);
    await expect(page.getByText(/Draf TRX-\d{8}-\d{3} berhasil dihapus\./)).toBeVisible();
});
```

Tes lama tidak perlu diubah dan harus tetap lulus.

## Kriteria selesai

- [ ] `npm run build` berhasil.
- [ ] `php artisan test` lulus semua. Jumlah tes bertambah 8.
- [ ] `npm run test:e2e` lulus semua. Jumlah tes bertambah 2.
- [ ] Di browser HP: transaksi baru bisa disimpan sebagai draf, draf muncul di daftar dengan badge "Draf", bisa dibuka lagi lewat "Ubah transaksi", lalu disimpan sebagai transaksi.
- [ ] Draf bisa dihapus lewat "Hapus draf".
- [ ] Transaksi "Belum bayar" bisa diubah dengan tombol "Simpan perubahan". Setelah ditandai "Lunas", tombol "Ubah transaksi" hilang.
- [ ] Laporan harian tidak menghitung draf, dan menghitung draf yang sudah dijadikan transaksi.
- [ ] Bagian bawah form rapi di lebar 375px: total di atas, dua tombol berdampingan di bawah.
- [ ] `README.md` proyek sudah diperbarui.

Pada laporan selesai, tambahkan baris `Hasil npm run test:e2e: <jumlah lulus dan gagal, atau "tidak dijalankan" beserta alasannya>` di bawah `Hasil npm run build`.

## Jangan lakukan

- Jangan membuat komponen Livewire baru. Pakai `TransactionForm` untuk transaksi baru dan untuk mengubah.
- Jangan membuat `Transaction` atau `TransactionItem` di komponen. Semua lewat `TransactionService`.
- Jangan menghapus `#[Locked]`.
- Jangan menampilkan tombol draf saat mengubah transaksi `confirmed`.
- Jangan mengubah `TransactionService`, rute, atau controller. Semua sudah dibuat di tugas 15.
- Jangan menghapus atau melemahkan tes agar lulus.
