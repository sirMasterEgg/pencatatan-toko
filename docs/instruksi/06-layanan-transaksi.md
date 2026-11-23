# Tugas 06: Layanan Simpan Transaksi

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 sampai 05 selesai.

## Tujuan

Satu kelas, `TransactionService`, yang menjadi satu-satunya pintu untuk menyimpan, membatalkan, dan mengubah status bayar transaksi. Belum ada halaman. Tugas ini hanya logika dan tes.

Ini bagian terpenting aplikasi. Ikuti setiap aturan dengan teliti.

## Yang dibuat

- `app/Services/TransactionService.php`
- `tests/Feature/TransactionServiceTest.php`

## Langkah

### 1. Kerangka kelas

```php
<?php

namespace App\Services;

class TransactionService
{
    public function __construct(
        private ProductService $products,
        private CustomerService $customers,
    ) {}

    public static function calculateSubtotal(float|int|string $quantity, int $unitPrice, int $discount = 0): int
    {
        return (int) round((float) $quantity * $unitPrice) - $discount;
    }

    public function create(array $data): Transaction { /* ... */ }
    // Sejak tugas 15: create(array $data, TransactionStatus $status = TransactionStatus::Confirmed),
    // ditambah update() dan deleteDraft(). Lihat 15-draf-dan-ubah-transaksi.md.

    public function cancel(Transaction $transaction, ?string $reason = null): Transaction { /* ... */ }

    public function updatePaymentStatus(Transaction $transaction, PaymentStatus $status): Transaction { /* ... */ }
}
```

### 2. Bentuk data masukan `create`

```php
[
    'transacted_at' => '2026-11-16 10:30',
    'channel' => 'store',
    'payment_status' => 'paid',
    'note' => null,

    // Salah satu dari tiga bentuk:
    'customer' => null,                                            // pembeli umum
    // 'customer' => ['id' => 5],                                  // pelanggan yang sudah ada
    // 'customer' => ['name' => 'Bu Sari', 'phone' => '0812-0000-1234', 'address' => null],

    'items' => [
        // Barang yang sudah ada:
        [
            'product_id' => 3,
            'quantity' => 2,
            'unit_price' => 115000,
            'discount' => 0,
            'price_mode' => 'this_only',   // atau 'new_price'
        ],
        // Barang baru:
        [
            'new_product' => ['name' => 'Gula', 'unit' => 'kg', 'cost_price' => null],
            'quantity' => 1,
            'unit_price' => 18000,
            'discount' => 0,
        ],
    ],
]
```

### 3. Validasi

Validasi dilakukan di dalam service dengan `Validator::make($data, $rules, $messages, $attributes)->validate()`. Kegagalan melempar `ValidationException`. Kunci galat memakai notasi titik, misalnya `items.0.quantity`, agar komponen Livewire di tugas 07 bisa menampilkannya.

| Kunci | Aturan |
|---|---|
| `transacted_at` | wajib, tanggal, `before_or_equal:now` |
| `channel` | wajib, `Rule::enum(Channel::class)` |
| `payment_status` | wajib, `Rule::enum(PaymentStatus::class)` |
| `note` | opsional, string, maksimal 1000 |
| `customer` | opsional, array |
| `customer.id` | opsional, integer, ada di `customers` |
| `customer.name` | wajib jika `customer` diisi dan `customer.id` kosong; string; maksimal 255 |
| `customer.phone` | opsional, string, maksimal 30 |
| `customer.address` | opsional, string, maksimal 255 |
| `items` | wajib, array, minimal 1 |
| `items.*.product_id` | opsional, integer, ada di `products` |
| `items.*.new_product` | opsional, array |
| `items.*.new_product.name` | wajib jika `items.*.product_id` kosong; string; maksimal 255 |
| `items.*.new_product.unit` | wajib jika `items.*.product_id` kosong; salah satu dari `config('shop.units')` |
| `items.*.new_product.cost_price` | opsional, integer, minimal 0 |
| `items.*.quantity` | wajib, numerik, `gt:0`. Sejak tugas 12: juga `decimal:0,3`, dan bilangan bulat untuk satuan di luar `config('shop.decimal_units')` |
| `items.*.unit_price` | wajib, integer, minimal 0 |
| `items.*.discount` | opsional, integer, minimal 0 |
| `items.*.price_mode` | opsional, salah satu dari `this_only`, `new_price`. Sejak tugas 18 juga `customer_price` |

Untuk `customer.name`, jangan memakai `required_without:customer.id`, karena aturan itu ikut gagal saat `customer` kosong. Pakai:

```php
'customer.name' => [
    Rule::requiredIf(fn () => is_array($data['customer'] ?? null) && empty($data['customer']['id'])),
    'nullable', 'string', 'max:255',
],
```

Untuk `items.*.new_product.name` dan `items.*.new_product.unit`, pakai `required_without:items.*.product_id`.

Pesan khusus:

- `items.required` dan `items.min`: `Transaksi harus punya minimal satu barang.`
- `transacted_at.before_or_equal`: `Tanggal transaksi tidak boleh di masa depan.`

Nama atribut untuk pesan: `items.*.quantity` menjadi `jumlah`, `items.*.unit_price` menjadi `harga satuan`, `items.*.discount` menjadi `diskon`, `items.*.new_product.name` menjadi `nama barang`, `items.*.new_product.unit` menjadi `satuan`, `customer.name` menjadi `nama pelanggan`.

Pemeriksaan tambahan setelah validasi dasar. Semuanya melempar `ValidationException::withMessages([...])`:

| Kondisi | Kunci galat | Pesan |
|---|---|---|
| Barang yang dipilih berstatus `inactive` atau `merged` | `items.{i}.product_id` | `Barang ini sudah tidak aktif.` |
| Subtotal baris kurang dari 0 | `items.{i}.discount` | `Diskon melebihi nilai baris.` |

### 4. Algoritma `create`

Semua langkah berikut dijalankan di dalam satu `DB::transaction(function () { ... })`. Jika ada yang gagal, tidak ada data yang tersimpan.

1. Validasi data (bagian 3).
2. Tentukan pelanggan:
   - `customer` kosong: `customer_id` bernilai `null`.
   - Ada `customer.id`: pakai id itu.
   - Selain itu: panggil `CustomerService::findOrCreate(name, phone, address)` dan pakai id hasilnya.
3. Buat nomor transaksi (bagian 5).
4. Buat baris `transactions` dengan `status` `confirmed`, `input_source` `form`, `total` `0`, dan data lain dari masukan. (Sejak tugas 15, status mengikuti parameter `$status`, bawaan `confirmed`.)
5. Untuk setiap item, sesuai urutan:

   **a. Jika item punya `product_id` (barang lama):**
   - Ambil barang beserta `currentPrice`. Jika statusnya bukan `active` atau `needs_review`, lempar galat.
   - `list_price` = `currentPrice->sell_price`, atau `null` jika barang belum punya harga.
   - Jika `list_price` bernilai `null`: panggil `ProductService::addPrice` dengan `unit_price` sebagai harga jual dan id transaksi sebagai sumber.
   - Jika `list_price` tidak `null`, `unit_price` berbeda dari `list_price`, DAN `price_mode` bernilai `new_price`: panggil `ProductService::addPrice` dengan `unit_price` sebagai harga jual, harga modal disalin dari `currentPrice->cost_price`, dan id transaksi sebagai sumber.
   - Catatan: tugas 18 (`18-harga-pelanggan-di-transaksi.md`) menambah mode `customer_price` dan kolom salinan `customer_price` di item.
   - Dalam kasus lain, jangan menyentuh `product_prices`.

   **b. Jika item tidak punya `product_id` (barang baru):**
   - Panggil `ProductService::findByNameAndUnit(name, unit)`.
   - Jika ketemu: perlakukan sebagai barang lama dengan `price_mode` `this_only`. Lanjut seperti langkah a. Jangan membuat barang ganda.
   - Jika tidak ketemu: panggil `ProductService::create` dengan nama, satuan, `unit_price` sebagai harga jual, `cost_price` dari masukan, status `ProductStatus::NeedsReview`, dan id transaksi sebagai sumber. `list_price` untuk item ini bernilai `null`.

   **c. Untuk kedua kasus:**
   - Hitung `subtotal` dengan `calculateSubtotal(quantity, unit_price, discount)`. Jika kurang dari 0, lempar galat.
   - Buat baris `transaction_items` dengan `product_id`, `quantity`, `unit` (salinan satuan barang), `unit_price`, `list_price`, `discount`, `subtotal`.

6. Jumlahkan semua `subtotal`, simpan ke `transactions.total`.
7. Kembalikan transaksi dengan `load('items.product', 'customer')`.

Catatan penting:

- `list_price` diambil SEBELUM harga baru ditambahkan. Nilainya adalah harga daftar yang berlaku sesaat sebelum transaksi ini.
- `discount` yang kosong dianggap `0`. `price_mode` yang kosong dianggap `this_only`.
- Jika barang baru yang sama (nama dan satuan sama) muncul di dua baris dalam satu transaksi, baris kedua otomatis menemukan barang yang dibuat baris pertama lewat `findByNameAndUnit`. Jangan membuat dua barang.

### 5. Nomor transaksi

Format: `TRX-YYYYMMDD-NNN`. Tanggal diambil dari `transacted_at`, bukan dari waktu simpan. `NNN` adalah nomor urut per tanggal, minimal 3 digit.

Cara membuat:

1. `$prefix = 'TRX-'.$transactedAt->format('Ymd').'-';`
2. Ambil `number` dari transaksi terakhir dengan awalan itu: `Transaction::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number')`.
3. Jika ada, nomor urut = angka setelah tanda `-` terakhir, ditambah 1. Jika tidak ada, nomor urut = 1.
4. `number = $prefix.str_pad((string) $urut, 3, '0', STR_PAD_LEFT)`.

Transaksi yang dibatalkan tetap ikut dihitung dalam urutan.

### 6. `cancel`

- Jika status bukan `confirmed`, lempar `ValidationException::withMessages(['status' => 'Hanya transaksi yang dikonfirmasi yang bisa dibatalkan.'])`.
- Ubah `status` menjadi `cancelled`, isi `cancelled_at` dengan `now()` dan `cancel_reason` dengan alasan.
- Barang, harga, dan pelanggan yang dibuat oleh transaksi itu TIDAK dihapus dan TIDAK diubah.
- Kembalikan transaksi.

### 7. `updatePaymentStatus`

- Jika status transaksi bukan `confirmed`, lempar `ValidationException::withMessages(['payment_status' => 'Status bayar hanya bisa diubah pada transaksi yang dikonfirmasi.'])`.
- Ubah `payment_status`. Jangan mengubah kolom lain.
- Kembalikan transaksi.

## Tes yang wajib ada

`tests/Feature/TransactionServiceTest.php`, memakai `RefreshDatabase`. Ambil service dengan `app(TransactionService::class)`.

**Skenario utama (contoh Bu Sari).** Siapkan barang "Mi Instan" satuan `dus` dengan harga daftar Rp118.000. Simpan transaksi: pelanggan baru Bu Sari nomor `0812-0000-1234`; item 1 Mi Instan jumlah 2 harga satuan 115000 mode `this_only`; item 2 barang baru "Gula" satuan `kg` jumlah 1 harga satuan 18000. Periksa:

1. `total` bernilai `248000`.
2. Ada pelanggan dengan `phone` `6281200001234`.
3. Ada barang "Gula" berstatus `needs_review` dengan `created_from_transaction_id` sama dengan id transaksi.
4. Barang "Gula" punya satu baris harga dengan `sell_price` `18000` dan `source_transaction_id` sama dengan id transaksi.
5. Item Mi Instan: `unit_price` `115000`, `list_price` `118000`, `subtotal` `230000`, `unit` `dus`.
6. Item Gula: `list_price` `null`, `subtotal` `18000`.
7. Harga daftar Mi Instan tetap `118000` dan jumlah baris harganya tetap satu.
8. `status` `confirmed` dan `number` berformat `TRX-YYYYMMDD-001`.

**Tes lain:**

9. Mode `new_price` dengan harga berbeda menambah satu baris harga. `currentPrice` barang menjadi harga baru, dan `cost_price` baris baru sama dengan harga modal sebelumnya. `list_price` di item tetap harga lama.
10. Mode `new_price` dengan harga yang sama dengan harga daftar tidak menambah baris harga.
11. Barang baru dengan nama "gula" saat "Gula" satuan `kg` sudah ada tidak membuat barang baru. Item terhubung ke barang yang ada.
12. Barang baru yang sama di dua baris dalam satu transaksi hanya membuat satu barang.
13. Transaksi tanpa pelanggan tersimpan dengan `customer_id` `null`.
14. Pelanggan baru dengan nomor HP yang sudah terdaftar memakai pelanggan yang ada.
15. `items` kosong melempar `ValidationException`.
16. `quantity` `0` melempar `ValidationException` dengan kunci `items.0.quantity`.
17. `transacted_at` besok melempar `ValidationException`.
18. Diskon lebih besar dari nilai baris melempar `ValidationException` dengan kunci `items.0.discount`.
19. Barang berstatus `inactive` melempar `ValidationException` dengan kunci `items.0.product_id`.
20. Keutuhan: transaksi dengan pelanggan baru, item pertama barang baru yang sah, dan item kedua dengan diskon melebihi nilai baris melempar `ValidationException` dan tidak menyimpan apa pun. Jumlah baris `customers`, `products`, `product_prices`, `transactions`, dan `transaction_items` sama dengan sebelum pemanggilan.
21. Jumlah desimal: `quantity` `0.5` dengan `unit_price` `18000` menghasilkan `subtotal` `9000`.
22. Dua transaksi pada tanggal yang sama mendapat nomor urut `001` dan `002`. Transaksi pada tanggal lain mulai lagi dari `001`.
23. `cancel` mengubah status menjadi `cancelled` dan mengisi `cancelled_at`. Barang dan harga yang dibuat transaksi itu masih ada.
24. `cancel` pada transaksi yang sudah dibatalkan melempar `ValidationException`.
25. `updatePaymentStatus` mengubah `unpaid` menjadi `paid` tanpa mengubah `total`.
26. Setelah barang dipakai di transaksi, permintaan `PUT /products/{id}` yang mengubah `unit` ditolak dengan galat pada `unit` (aturan dari tugas 04, pakai `actingAs`).

## Kriteria selesai

- [ ] `php artisan test` lulus semua, termasuk 26 tes di atas.
- [ ] Seluruh isi `create` berada di dalam satu `DB::transaction`.
- [ ] `TransactionService` tidak membuat `Product` atau `ProductPrice` secara langsung. Semuanya lewat `ProductService`.
- [ ] `TransactionService` tidak membuat `Customer` secara langsung. Semuanya lewat `CustomerService`.
- [ ] Tidak ada controller, rute, atau view baru.

## Jangan lakukan

- Jangan membuat fitur ubah isi transaksi. Fitur itu dibuat nanti di tugas 15 dengan aturan yang jelas.
- Jangan menghapus baris transaksi saat dibatalkan.
- Jangan membuat tabel pembayaran atau cicilan.
- Jangan mencatat stok.
- Jangan memakai `float` untuk menyimpan uang. `round()` hanya dipakai saat menghitung subtotal.
