# Tugas 02: Database dan Model

Baca dulu: `00-aturan-umum.md`
Prasyarat: tugas 01 selesai.

## Tujuan

Semua tabel, enum, model, relasi, dan factory tersedia. Belum ada halaman.

## Langkah

### 1. Enum

Buat empat enum di `app/Enums`. Semuanya enum string dengan method `label()` yang mengembalikan teks bahasa Indonesia.

`ProductStatus`:

```php
<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case NeedsReview = 'needs_review';
    case Merged = 'merged';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::NeedsReview => 'Perlu dirapikan',
            self::Merged => 'Digabung',
            self::Inactive => 'Nonaktif',
        };
    }
}
```

Buat tiga enum lain dengan pola yang sama:

| Enum | Case dan nilai | Label |
|---|---|---|
| `TransactionStatus` | `Draft = 'draft'`, `Confirmed = 'confirmed'`, `Cancelled = 'cancelled'` | Draf, Dikonfirmasi, Dibatalkan |
| `PaymentStatus` | `Paid = 'paid'`, `Partial = 'partial'`, `Unpaid = 'unpaid'` | Lunas, Sebagian, Belum bayar |
| `Channel` | `Store = 'store'`, `Whatsapp = 'whatsapp'`, `Phone = 'phone'` | Toko, WhatsApp, Telepon |

### 2. Buat model dan migrasi

Jalankan sesuai urutan ini. Urutan menentukan urutan migrasi.

```bash
php artisan make:model Product -mf
php artisan make:model ProductPrice -mf
php artisan make:model Customer -mf
php artisan make:model Transaction -m
php artisan make:model TransactionItem -m
```

### 3. Isi migrasi

Isi bagian `Schema::create` pada tiap migrasi persis seperti di bawah.

`products`:

```php
$table->id();
$table->string('name');
$table->string('unit', 20);
$table->string('category')->nullable();
$table->string('code')->nullable()->unique();
$table->string('status', 20)->default('needs_review')->index();
$table->foreignId('merged_into_id')->nullable()->constrained('products')->nullOnDelete();
$table->unsignedBigInteger('created_from_transaction_id')->nullable()->index();
$table->timestamps();
$table->index('name');
```

`product_prices`:

```php
$table->id();
$table->foreignId('product_id')->constrained()->restrictOnDelete();
$table->unsignedBigInteger('sell_price');
$table->unsignedBigInteger('cost_price')->nullable();
$table->dateTime('effective_from')->index();
$table->unsignedBigInteger('source_transaction_id')->nullable()->index();
$table->string('note')->nullable();
$table->timestamps();
```

`customers`:

```php
$table->id();
$table->string('name');
$table->string('phone', 20)->nullable()->unique();
$table->string('address')->nullable();
$table->string('note')->nullable();
$table->timestamps();
```

`transactions`:

```php
$table->id();
$table->string('number', 30)->unique();
$table->dateTime('transacted_at')->index();
$table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
$table->string('channel', 20);
$table->string('input_source', 20)->default('form');
$table->string('status', 20)->default('confirmed')->index();
$table->string('payment_status', 20);
$table->unsignedBigInteger('total')->default(0);
$table->text('note')->nullable();
$table->dateTime('cancelled_at')->nullable();
$table->string('cancel_reason')->nullable();
$table->timestamps();
```

`transaction_items`:

```php
$table->id();
$table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
$table->foreignId('product_id')->constrained()->restrictOnDelete();
$table->decimal('quantity', 12, 3);
$table->string('unit', 20);
$table->unsignedBigInteger('unit_price');
$table->unsignedBigInteger('list_price')->nullable();
$table->unsignedBigInteger('discount')->default(0);
$table->unsignedBigInteger('subtotal');
$table->timestamps();
```

Catatan:

- `created_from_transaction_id` dan `source_transaction_id` sengaja tanpa foreign key, karena tabel `transactions` dibuat setelahnya.
- Kolom `merged_into_id`, `input_source`, dan nilai status `draft` serta `merged` disiapkan untuk tahap berikutnya. Tidak ada layar yang memakainya di tahap 1.

### 4. Isi model

Tiru gaya `app/Models/User.php`: atribut `#[Fillable([...])]` dan method `casts()`.

`Product`

- Fillable: `name`, `unit`, `category`, `code`, `status`, `merged_into_id`, `created_from_transaction_id`.
- Cast: `status` ke `ProductStatus::class`.
- Relasi: `prices()` hasMany `ProductPrice`; `items()` hasMany `TransactionItem`; `currentPrice()` seperti kode di bawah.
- Scope `selectable`: hanya status `active` dan `needs_review`.

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

public function currentPrice(): HasOne
{
    return $this->hasOne(ProductPrice::class)->ofMany(
        ['effective_from' => 'max', 'id' => 'max'],
        fn (Builder $query) => $query->where('effective_from', '<=', now()),
    );
}

public function scopeSelectable(Builder $query): void
{
    $query->whereIn('status', [ProductStatus::Active, ProductStatus::NeedsReview]);
}
```

`ProductPrice`

- Fillable: `product_id`, `sell_price`, `cost_price`, `effective_from`, `source_transaction_id`, `note`.
- Cast: `effective_from` ke `datetime`, `sell_price` dan `cost_price` ke `integer`.
- Relasi: `product()` belongsTo `Product`.
- Baris tidak boleh diubah atau dihapus. Tambahkan:

```php
protected static function booted(): void
{
    static::updating(fn () => throw new \LogicException('Riwayat harga tidak boleh diubah.'));
    static::deleting(fn () => throw new \LogicException('Riwayat harga tidak boleh dihapus.'));
}
```

`Customer`

- Fillable: `name`, `phone`, `address`, `note`.
- Relasi: `transactions()` hasMany `Transaction`.

`Transaction`

- Fillable: `number`, `transacted_at`, `customer_id`, `channel`, `input_source`, `status`, `payment_status`, `total`, `note`, `cancelled_at`, `cancel_reason`.
- Cast: `transacted_at` dan `cancelled_at` ke `datetime`; `status` ke `TransactionStatus::class`; `payment_status` ke `PaymentStatus::class`; `channel` ke `Channel::class`; `total` ke `integer`.
- Relasi: `customer()` belongsTo `Customer`; `items()` hasMany `TransactionItem`.
- Scope `confirmed`: hanya status `confirmed`.

`TransactionItem`

- Fillable: `transaction_id`, `product_id`, `quantity`, `unit`, `unit_price`, `list_price`, `discount`, `subtotal`.
- Cast: `quantity` ke `decimal:3`; `unit_price`, `list_price`, `discount`, `subtotal` ke `integer`.
- Relasi: `transaction()` belongsTo `Transaction`; `product()` belongsTo `Product`.

### 5. Factory

Model yang punya factory wajib memakai trait `HasFactory`, seperti di `User.php`.

| Factory | Nilai bawaan |
|---|---|
| `ProductFactory` | `name` dari `fake()->unique()->words(2, true)`, `unit` `'pcs'`, `status` `ProductStatus::Active` |
| `ProductPriceFactory` | `product_id` dari `Product::factory()`, `sell_price` `10000`, `cost_price` `8000`, `effective_from` `now()->subDay()` |
| `CustomerFactory` | `name` dari `fake()->name()`, `phone` dari `'628'.fake()->unique()->numerify('##########')` |

Tidak ada factory untuk `Transaction` dan `TransactionItem`. Di tugas berikutnya, tes membuat transaksi lewat `TransactionService`.

## Tes yang wajib ada

`tests/Feature/ProductPriceModelTest.php`, memakai `RefreshDatabase`:

1. Barang dengan tiga harga: kemarin Rp10.000, satu jam lalu Rp12.000, besok Rp15.000. `currentPrice->sell_price` harus `12000`.
2. Barang tanpa harga: `currentPrice` harus `null`.
3. Mengubah `sell_price` pada baris harga yang sudah tersimpan melempar `LogicException`.
4. Menghapus baris harga melempar `LogicException`.
5. `Product::selectable()` tidak memuat barang berstatus `inactive` dan `merged`.

## Kriteria selesai

- [ ] `php artisan migrate:fresh` berhasil.
- [ ] `php artisan test` lulus semua.
- [ ] Ada empat enum di `app/Enums`, masing-masing punya `label()`.
- [ ] Ada lima model dengan relasi sesuai daftar.
- [ ] Tidak ada kolom uang bertipe `float` atau `decimal`.

## Jangan lakukan

- Jangan menambah tabel atau kolom di luar daftar.
- Jangan membuat tabel `units`, `categories`, `attachments`, atau `stocks`.
- Jangan membuat controller, rute, atau view.
- Jangan memakai `softDeletes`.
