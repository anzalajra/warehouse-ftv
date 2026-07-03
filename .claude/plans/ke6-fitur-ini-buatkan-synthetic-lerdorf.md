# Rencana 6 Fitur Gearent — Executable per Chatroom

## Context

Gearent (Laravel 12 + Filament 4, rental/warehouse SaaS) akan ditambah 6 fitur untuk menutup gap komersial vs Booqable. Dokumen ini dipecah agar **tiap fitur bisa dieksekusi di chatroom terpisah** — setiap bagian berdiri sendiri (tujuan, migrasi, perubahan kode, langkah, verifikasi). Keputusan desain sudah dikonfirmasi user:

1. **Multi-tier pricing** → periode **per-rental** (satu `pricing_period` per rental).
2. **Product performance report** → **sempurnakan** `Reports.php`/`InventoryReportService` yang sudah ada (bukan halaman baru).
3. **Analitik produk-sentris** → digabung ke #2 (satu workstream).
4. **Recurring rental** → generate **draft quotation** untuk review admin (tanpa auto-charge).
5. **Delivery routing** → **MVP operasional** (alamat + metode + driver/escort + halaman jadwal, tanpa peta).
6. **Custom fields** pada Product & Rental → mirror pola custom-fields customer yang sudah ada.

### Batasan lingkungan (WAJIB dibaca tiap chatroom)
- Lokal **tidak ada `vendor/`** dan **tidak ada `storage/installed`** → `php artisan …`, migrate, test, pint **tidak jalan lokal**. Verifikasi = **static**: `& 'C:\xampp\php\php.exe' -l <file>` per file PHP yang diubah. XAMPP CLI **tanpa ext-gd**.
- **Jangan** edit `resources/css/filament/admin/theme.css` atau file Vite entry (butuh rebuild yang tak bisa jalan di sini). Semua UI di plan ini pakai **Blade + Alpine + inline `<style>`** saja.
- Migrasi baru: ikuti prefix tanggal, urut setelah `2026_07_01_000005`. Pakai **`2026_07_04_0000NN_*`**. Tiap fitur punya file migrasi sendiri → aman dijalankan paralel di chatroom berbeda.
- Setelah `RentalItem` berubah, **jangan** recalc total manual — `RentalItem::$touches=['rental']` memicu `RentalObserver::recalculateTotals()` otomatis (lihat CLAUDE.md).
- Semua fitur di bawah **hanya Blade** (tanpa rebuild Vite). Langkah runtime server: hanya `php artisan migrate` (+ `optimize:clear`).

### Anchor kalkulasi harga (dipakai fitur #1)
- `app/Models/RentalItem.php` ~L32–37 `booted()` saving hook: `subtotal = daily_rate × days × (1 − discount%)`. **Ini anchor**; kolom `days` sudah ada.
- `app/Observers/RentalObserver.php` ~L72 `recalculateTotals()` menjumlah `items.subtotal`, lalu diskon berlapis + pajak.
- `app/Livewire/Admin/RentalEditor.php`: `days()` computed ~L294 (`ceil(diffInHours/24)`), `subtotal()` ~L1620 (`daily_rate×qty×days`), `totals()` ~L1634, save ~L1504.
- Storefront: `app/Models/Cart.php` boot (`subtotal=daily_rate×days`), `CartController::add` ~L262, `CheckoutController` ~L52–90.

---

## FITUR 1 — Multi-tier pricing (per-rental period)

**Ide inti (kunci agar minim ubah downstream):** simpan `pricing_period` di level Rental. Makna kolom yang sudah ada **digeneralkan**: `RentalItem.daily_rate` = *tarif per periode terpilih*, `RentalItem.days` = *jumlah periode*. Dengan begitu `daily_rate × days` di anchor & observer **tidak berubah** — hanya pemilihan tarif + hitung jumlah periode + label yang ditambah.

### Migrasi (`2026_07_04_000001_add_multitier_pricing.php`)
- `products`: tambah `hourly_rate`, `weekly_rate`, `monthly_rate` — `decimal(15,2) nullable`.
- `product_variations`: tambah 3 kolom rate yang sama (nullable, override seperti `daily_rate`).
- `rentals`: `pricing_period` string default `'day'`.
- `rental_items`: `rate_type` string default `'day'` (audit periode yang ditagih).
- `carts`: `pricing_period` string default `'day'`.

### Model
- `app/Models/Product.php`: tambah 3 kolom ke `$fillable` + `$casts` (`decimal:2`). Tambah helper:
  ```php
  const PERIODS = ['hour','day','week','month'];
  public function rateFor(string $period): float {
      return match ($period) {
          'hour'  => (float) ($this->hourly_rate  ?? ($this->daily_rate / 8)),   // fallback 8 jam kerja
          'week'  => (float) ($this->weekly_rate  ?? ($this->daily_rate * 7)),
          'month' => (float) ($this->monthly_rate ?? ($this->daily_rate * 30)),
          default => (float) $this->daily_rate,
      };
  }
  ```
  (ProductVariation dapat helper serupa; fallback ke rate produk induk saat kolom variasi null.)
- `app/Models/Rental.php`: `pricing_period` ke `$fillable`. Tambah static:
  ```php
  public static function periodsBetween($start, $end, string $period): int {
      $s = Carbon::parse($start); $e = Carbon::parse($end);
      $hours = max(1, $s->diffInHours($e));
      return match ($period) {
          'hour'  => (int) ceil($hours),
          'week'  => (int) max(1, ceil($hours / 24 / 7)),
          'month' => (int) max(1, ceil($hours / 24 / 30)),
          default => (int) max(1, ceil($hours / 24)),
      };
  }
  public function periodLabel(): string { return ['hour'=>'jam','day'=>'hari','week'=>'minggu','month'=>'bulan'][$this->pricing_period ?? 'day']; }
  ```
- `app/Models/RentalItem.php`: `rate_type` ke `$fillable`. **Anchor formula tak berubah.**
- `app/Models/Cart.php`: `pricing_period` ke `$fillable`; `calculateDays()` diganti/dibungkus jadi period-aware pakai `Rental::periodsBetween(...,$this->pricing_period)`.

### Admin — `RentalEditor.php` + blade
- Property baru `public string $pricing_period = 'day';` (init dari `record->pricing_period` di mount).
- `days()` computed → hitung via `Rental::periodsBetween($start,$end,$pricing_period)` (rename intent = "jumlah periode", nama method boleh tetap `days`).
- Saat item ditambah dari katalog (sekitar L527/L541 yang set `price`): ambil `$product->rateFor($this->pricing_period)` (variation dulu, fallback produk). Simpan juga peta rate `['hour'=>..,'day'=>..,'week'=>..,'month'=>..]` di baris item agar ganti periode = re-map tanpa query.
- Tambah handler `updatedPricingPeriod()`: loop `$items`, set `daily_rate = rates[period]`.
- `save()`/`persistInline()` (~L1504): set `$rental->pricing_period`, dan tiap item `rate_type = $this->pricing_period`. Formula subtotal item tetap.
- Blade `resources/views/livewire/admin/rental-editor.blade.php`: 
  - Segmented control periode (Jam/Hari/Minggu/Bulan) `wire:model.live="pricing_period"` dekat date picker (desktop + mobile).
  - Relabel "Daily Rate"→"Tarif / {{ periode }}", kolom "hari"→`$this->periodLabel`, ringkasan "× N hari"→"× N {{ periodLabel }}".

### Admin — `ProductForm.php`
- Tambah 3 `TextInput` (`hourly_rate`,`weekly_rate`,`monthly_rate`) prefix `Rp`, `numeric()`, `placeholder('Kosongkan = otomatis dari harga harian')`, di grup harga (dekat `daily_rate` ~L119). Tambah juga ke Repeater `variations` (~L166).

### Storefront (MVP)
- `resources/views/frontend/catalog/show.blade.php`: selector periode sebelum tombol add-to-cart (kirim `pricing_period` ke `CartController::add`).
- `CartController::add` (~L146,262): `pricing_period` dari request (default `day`); `$dailyRate = $product->rateFor($period)`; `$days = Rental::periodsBetween($start,$end,$period)`. Enforce **satu periode per cart** (jika cart sudah ada item beda periode → tolak/replace, pesan flash).
- `CheckoutController` (~L52): baca `pricing_period` dari cart lines; set `Rental::create([... 'pricing_period'=>$period])`.
- PDF (`resources/views/pdf/*`) & badge storefront: relabel "hari" pakai `pricing_period` (opsional, boleh menyusul).

### Langkah eksekusi
1. Tulis migrasi → 2. Product/ProductVariation model+helper → 3. Rental/RentalItem/Cart model → 4. ProductForm → 5. RentalEditor (PHP) → 6. rental-editor.blade → 7. Storefront cart/checkout + catalog blade → 8. `php -l` semua file.

### Verifikasi (server)
`php artisan migrate` → buat produk isi weekly/monthly kosong (cek fallback) & terisi → rental editor: ganti periode, pastikan tarif & total ikut; simpan → cek `rental_items.rate_type` & `rentals.pricing_period`; PDF quotation tampil label benar. Storefront: tambah item dgn periode, checkout, total match.

---

## FITUR 2+3 — Sempurnakan Product Performance & analitik produk (Reports.php)

**Sudah ada:** `app/Services/InventoryReportService.php` menghitung `unitMetrics()`, `productSummary()`, `productPerformance()` (utilisasi %, revenue/unit, ROI, lifetime). `app/Filament/Pages/Reports.php` sudah punya tab **Inventory** yang menampilkan "product performance" + export CSV/PDF (`export()`), state via `#[Url]`, sort `$prodSort`. **Tugas = perkaya, bukan bikin baru.**

### Perubahan
- `app/Services/InventoryReportService.php` → `productPerformance($start,$end)`: pastikan/ tambah field: `rental_count` (jumlah RentalItem realized via `withCount` difilter `RentalReportService::REALIZED_STATUSES`), `idle_units`, `avg_utilization`, `revenue_per_unit`, `avg_roi`. Tambah flag turunan `is_underperforming` (mis. `avg_utilization < 15` dalam periode) untuk highlight.
- `app/Filament/Pages/Reports.php`: 
  - Tambah opsi sort produk (`revenue`, `utilization`, `rental_count`, `roi`) via `$prodSort` (sudah ada—perluas match-nya).
  - Tambah ringkasan KPI produk di atas tabel: Top produk (revenue), Produk paling sering disewa (rental_count), Produk idle/underperforming (list).
  - Tambah dataset export baru "product_performance" di `export()` (CSV+PDF) — reuse mekanisme yang ada.
- Blade `resources/views/filament/pages/reports.blade.php` (bagian Inventory): 
  - Kolom tabel: Produk | #Sewa | Revenue | Rev/Unit | Utilisasi% | ROI% | Idle. Badge merah utk `is_underperforming`, bar utilisasi (div width %).
  - Sub-tab Alpine: "Top performer" / "Underperformer".
- (Opsional, jika mau nilai jual dashboard) — **dilewati** sesuai pilihan user ("sempurnakan yang ada", tanpa widget/halaman baru).

### Langkah eksekusi
1. Perluas `productPerformance()` (tambah rental_count + is_underperforming) → 2. Reports.php: sort options + KPI getters + export dataset → 3. reports.blade Inventory section → 4. blade export template baru di `resources/views/filament/pages/reports/` → 5. `php -l`.

### Verifikasi (server)
Buka `/admin/reports` tab Inventory: ganti preset tanggal, sort by utilisasi/revenue/#sewa; export CSV & PDF berisi kolom baru; produk tanpa sewa muncul sebagai idle/underperforming.

---

## FITUR 4 — Recurring / subscription rental (draft quotation)

**Perilaku:** rental yang ditandai recurring akan **di-clone jadi quotation baru** oleh command terjadwal saat `recurrence_next_date` tiba; admin review/konfirmasi manual. Tanpa auto-charge.

### Migrasi (`2026_07_04_000002_add_recurrence_to_rentals.php`)
`rentals`: `is_recurring` bool default false; `recurrence_interval` string nullable (`'weekly'|'monthly'`); `recurrence_next_date` date nullable; `recurrence_end_date` date nullable; `recurrence_parent_id` FK self nullable (`nullOnDelete`).

### Model — `app/Models/Rental.php`
- 5 kolom ke `$fillable`; cast tanggal.
- Relasi `recurrenceParent()` / `recurrenceChildren()`.
- Helper clone:
  ```php
  public function replicateForRecurrence(): self {
      $len = $this->start_date->diffInDays($this->end_date);
      $newStart = Carbon::parse($this->recurrence_next_date);
      $new = $this->replicate([
          'rental_code','status','returned_date','activity_log',
          'revenue_recognized_at','recurrence_parent_id',
      ]);
      $new->status = self::STATUS_QUOTATION;
      $new->start_date = $newStart;
      $new->end_date = $newStart->copy()->addDays($len);
      $new->recurrence_parent_id = $this->id;
      $new->is_recurring = false;               // anak bukan sumber recurring
      $new->recurrence_interval = null;
      $new->recurrence_next_date = null;
      $new->save();                              // rental_code via observer/boot yang ada
      foreach ($this->items as $it) {
          $copy = $it->replicate(['product_unit_id']); // slot kosong, admin assign unit saat konfirmasi
          $copy->rental_id = $new->id;
          $copy->save();                          // subtotal via RentalItem hook, total via observer
      }
      return $new;
  }
  ```
  (Sengaja `product_unit_id` di-null-kan → jadi ghost slot; ketersediaan unit periode baru divalidasi saat admin konfirmasi, konsisten dgn pola ghost-slot di CLAUDE.md.)

### Command — `app/Console/Commands/GenerateRecurringRentals.php`
- `signature = 'rentals:generate-recurring'`. Query: `is_recurring=true`, `recurrence_next_date <= today`, `(recurrence_end_date null OR >= recurrence_next_date)`.
- Per rental sumber: `replicateForRecurrence()`; majukan `recurrence_next_date` (+1 week/month sesuai interval); jika lewat `recurrence_end_date` → set `is_recurring=false`. Notifikasi admin (pola `SendRentalReminders.php`: `Notification::send($admins, …)`), `updateQuietly` utk field recurrence.
- Daftarkan di `routes/console.php`: `Schedule::command('rentals:generate-recurring')->dailyAt('06:00')->withoutOverlapping()->runInBackground();`

### Admin UI
- `RentalEditor.php` + blade: section "Langganan / Recurring" (toggle `is_recurring`, select interval, date `recurrence_end_date`). Persist di `save()`/`persistInline()`. `recurrence_next_date` diisi = `end_date`+1 saat aktif (atau field manual).
- `RentalsTable.php`: badge/ikon recurring + filter "Recurring only". Di ViewRental tampilkan induk/anak (link `recurrence_parent_id`).

### Langkah eksekusi
1. Migrasi → 2. Rental model (fillable+relasi+`replicateForRecurrence`) → 3. Command → 4. Daftar schedule → 5. RentalEditor PHP+blade → 6. RentalsTable badge/filter → 7. `php -l`.

### Verifikasi (server)
Tandai satu rental recurring, set `recurrence_next_date`=hari ini → `php artisan rentals:generate-recurring` → muncul quotation baru (ghost slot, dates bergeser), induk `recurrence_next_date` maju, admin dapat notifikasi. Konfirmasi quotation baru jalan normal.

---

## FITUR 5 — Delivery routing / logistik (MVP operasional)

**Cakupan:** alamat kirim + metode fulfillment di rental; assign **driver** & **escort/pengawal alat** ke Delivery; halaman **Jadwal Pengiriman** per hari (grouped by driver) + status. Tanpa peta/optimasi rute.

### Migrasi
- `2026_07_04_000003_add_fulfillment_to_rentals.php` — `rentals`: `fulfillment_method` string default `'pickup'` (`pickup|delivery`); `delivery_address` text nullable; `delivery_contact` string nullable; `delivery_notes` text nullable.
- `2026_07_04_000004_add_routing_to_deliveries.php` — `deliveries`: `driver_id` FK users nullable; `escort_id` FK users nullable; `scheduled_at` datetime nullable; `address` text nullable; `sort_order` int default 0.

### Model
- `app/Models/Rental.php`: 4 kolom → `$fillable`.
- `app/Models/Delivery.php`: 5 kolom → `$fillable`; relasi `driver()` & `escort()` (`belongsTo(User::class,'driver_id'|'escort_id')`). Di `createDeliveries()` (Rental) isi `address` = `rental->delivery_address` & `scheduled_at` default = `rental->start_date` (out) / `end_date` (in).
- (Opsional) `database/seeders/RoleSeeder.php`: tambah role `driver` (view/update Delivery). MVP boleh assign user role apa saja `admin|staff|driver`.

### Admin
- `app/Filament/Resources/Deliveries/DeliveryResource.php`: 
  - Form: select `driver_id` & `escort_id` (options user berrole admin/staff/driver), `DateTimePicker scheduled_at`, `Textarea address`.
  - Table: kolom Driver, Escort, Scheduled, Method (dari rental), Status; filter tanggal (`scheduled_at`), driver, status.
- **Halaman baru** `app/Filament/Pages/DeliverySchedule.php` (grup nav "Rentals"/"Logistik", pola custom-page-blade seperti Reports.php):
  - `#[Url] public string $date` (default today). Getter `deliveries()`: `Delivery::whereDate('scheduled_at',$date)->with('rental.user','driver','items')->orderBy('driver_id')->orderBy('sort_order')`.
  - Blade `resources/views/filament/pages/delivery-schedule.blade.php`: date picker + list dikelompok per driver (kartu: no rental, customer, alamat, jam, jumlah item, status badge, tombol buka rental/proses). Alpine untuk collapse per-driver. Aksi ubah status inline (`wire:click`).
- Rental editor / ViewRental: section "Pengiriman" (toggle `fulfillment_method`, `delivery_address` prefill dari `user->address`, `delivery_contact`, `delivery_notes`). Persist di save.

### Storefront (MVP)
- `resources/views/frontend/checkout/index.blade.php`: radio Ambil sendiri / Diantar; jika diantar → textarea alamat (prefill `auth()->user()->address`) + kontak.
- `CheckoutController::process`: simpan `fulfillment_method`,`delivery_address`,`delivery_contact` ke rental.

### Langkah eksekusi
1. 2 migrasi → 2. Rental/Delivery model + relasi + `createDeliveries()` isi address/scheduled → 3. (opsional) RoleSeeder driver → 4. DeliveryResource form+table+filter → 5. DeliverySchedule page + blade → 6. RentalEditor/ViewRental section pengiriman → 7. Checkout storefront → 8. `php -l`.

### Verifikasi (server)
`php artisan migrate` (+ `db:seed --class=RoleSeeder` bila tambah role). Buat rental metode "delivery" + alamat → pickup operation buat Delivery ber-address → assign driver/escort + scheduled_at → buka **Jadwal Pengiriman**, pilih tanggal: delivery muncul terkelompok per driver, ubah status. Checkout storefront kirim alamat tersimpan.

---

## FITUR 6 — Custom fields pada Product & Rental

**Mirror pola customer yang ada:** kolom `custom_fields` JSON (cast `array`), definisi field disimpan sebagai `Setting` (Repeater di Settings), render dinamis di form (baca Setting → build komponen), path `custom_fields.{name}`. Referensi: `CustomerForm.php` L18–75, `RegistrationSettings.php` L79–115, `User.php` cast L77.

### Migrasi (`2026_07_04_000005_add_custom_fields_product_rental.php`)
- `products`: `custom_fields` json nullable.
- `rentals`: `custom_fields` json nullable.

### Model
- `Product.php` & `Rental.php`: `custom_fields` → `$fillable` + `$casts => 'array'`.

### Settings (definisi field)
- Simpan 2 key Setting: `product_custom_fields`, `rental_custom_fields` (JSON, sama skema seperti `registration_custom_fields`: `label,name,type,options,required`).
- Tempat kelola: extend `app/Filament/Clusters/Settings/Pages/ProductSetup.php` (tambah section "Custom Fields Produk") + section baru untuk rental (di ProductSetup atau `RentalSettings.php`). Reuse Repeater dari `RegistrationSettings.php`.

### Render form (dinamis — mirror CustomerForm)
- `app/Filament/Resources/Products/Schemas/ProductForm.php`: helper yang baca `Setting::get('product_custom_fields','[]')`, loop → `TextInput/Select/Radio/Checkbox/Textarea` dengan `->statePath('custom_fields.'.$field['name'])` (Filament auto-nest JSON). Bungkus dalam `Section::make('Informasi Tambahan')`.
- `RentalEditor.php` + blade: section "Informasi Tambahan" render field dari `Setting::get('rental_custom_fields')`, `wire:model="custom_fields.{name}"`; persist di `save()`/`persistInline()` (tambahkan `custom_fields` ke payload create/fill). Init dari `record->custom_fields` di mount.

### Tampilan
- `ViewRental` blade + PDF quotation/invoice (opsional): tampilkan `custom_fields` non-kosong (loop label→value).
- (Opsional) storefront checkout: render `rental_custom_fields` sebagai input (mis. "Nama acara", "Lokasi syuting") → `CheckoutController` simpan ke `custom_fields`.

### Langkah eksekusi
1. Migrasi → 2. Product/Rental model cast+fillable → 3. Settings page(s) Repeater (product+rental) → 4. ProductForm dynamic section → 5. RentalEditor PHP+blade section+persist → 6. (opsional) ViewRental/PDF/checkout display → 7. `php -l`.

### Verifikasi (server)
Settings → definisikan 1 field produk (mis. "Berat") & 1 field rental (mis. "Nama Acara") → edit produk isi field → simpan, cek `products.custom_fields` JSON. Buat/edit rental isi field → cek `rentals.custom_fields`; tampil di ViewRental.

---

## Urutan & independensi antar-chatroom
- **Independen** (bisa paralel): masing-masing punya migrasi sendiri; konflik file minim. Yang menyentuh `RentalEditor.php`/`rental-editor.blade.php` **beririsan**: Fitur 1 (periode), 4 (recurring section), 5 (pengiriman section), 6 (custom fields section). Jika dikerjakan paralel, koordinasikan merge di `save()`/`persistInline()` & blade (tiap fitur = section terpisah, konflik hanya di titik simpan).
- **Rekomendasi urutan**: 1 (pricing, paling inti) → 6 (custom fields, pola jelas) → 5 (delivery) → 4 (recurring) → 2+3 (report, paling ringan). 
- **Runtime server tiap selesai**: `php artisan migrate` lalu `php artisan optimize:clear`. Tidak perlu rebuild Vite (semua Blade). Fitur 4 juga jalankan `rentals:generate-recurring` manual sekali untuk uji.

## Verifikasi global
Tidak ada test otomatis yang di-set untuk ini; verifikasi manual per bagian "Verifikasi" di atas + `php -l` pada setiap file PHP yang diubah (Blade `@if/@foreach` tak bisa `-l`; blok `@php` inline bisa). Sweep exhaustiveness bila menambah nilai enum status (lihat CLAUDE.md) — relevan bila kelak menambah status baru (fitur ini tidak menambah status rental).
