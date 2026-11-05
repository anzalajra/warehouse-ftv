# Audit RAM — 5 November 2026

Audit pada baseline Git `6c04d51`, PHP lokal 8.5.12, SQLite in-memory. Pemindaian statis mencakup sekitar 840 berkas produksi, konfigurasi, migrasi, tampilan, dan aset pada `app/`, `resources/`, `routes/`, `config/`, `database/`, `desktop-kiosk/src/`, dan `public/vendor/luckprinter/`. Jalur yang memuat koleksi besar, relasi, berkas, buffer gambar, cache, dan timer kemudian ditelusuri lebih rinci. Dependensi Composer/npm serta aset hasil build tidak diedit langsung.

Ini audit kode dan benchmark lokal, bukan pengukuran RSS container produksi atau uji beban multiuser. Tidak ada perubahan pada batas RAM PHP, jumlah worker, frekuensi polling, kualitas kamera, atau aturan bisnis.

## Perubahan yang diterapkan

| Area | Perubahan | Perilaku yang dipertahankan |
| --- | --- | --- |
| Ringkasan rental | Database mengelompokkan status dan tanggal serta menghitung jumlah/nilai sebelum mengirim hasil ke PHP. | Semua status, nilai bruto/neto, rentang tanggal, dan hari kosong. Satu kueri. |
| Laporan keterlambatan | Total seluruh rental dihitung di database; hanya 50 detail yang dimuat, dengan kolom yang dibutuhkan. | Denda tetap berasal dari kolom otoritatif `rentals.late_fee`; tidak dihitung ulang. |
| Laporan diskon/pelanggan/pembayaran | Membatasi kolom rental, relasi promo, pengguna, dan invoice. | `discountBreakdown()` tetap menjadi sumber diskon; `balance` tetap memperhitungkan PPh 23. |
| Serah-terima | Distribusi tipe delivery dan kondisi barang dihitung dengan agregat database. | Filter tanggal/tipe dan definisi jumlah barang yang masih di luar. |
| Stok | Menghitung kelompok produk/gudang/status sebelum membentuk ringkasan PHP. | Jumlah stok, unit tanpa gudang, seluruh status dan urutan kelompok berdasarkan kemunculan pertama. |
| Metrik inventaris | Memilih kolom yang dipakai kalkulasi; cache menjadi instance `scoped`, bukan array statis selama proses hidup. | Kueri agregat yang sudah ada serta pemakaian ulang hasil dalam satu request/job. Nilai buku tetap memakai accessor model. |
| Ringkasan pajak | `COUNT`/`SUM` di database menggantikan pemuatan seluruh invoice. | Filter invoice taxable, rentang tanggal, pengecualian draft/cancelled. |
| Ketersediaan unit | Hanya memuat kolom periode, status, pengembalian, dan ID yang diperlukan. Dokumen/snapshot Mufise serta catatan besar tidak ikut dibaca. | Partial return, perpanjangan, barang terlambat yang masih di luar, dan kit tertaut tetap memakai `RentalOccupancyService`. Jumlah kueri tetap. |
| Booking komputer | Memproses maksimal 200 model per batch menggunakan `lazyById`. | `save()`/observer tetap berjalan; perubahan status tidak membuat baris terlewat; penghitung tidak menjalankan ulang koleksi lazy. |
| Pengaturan | Delapan halaman setting serta `Setting::getGroup()` membaca langsung pasangan key/value. | Nilai dan urutan form tetap; tidak membentuk model Setting yang kemudian dibuang. |
| Scanner pickup/return | Lifecycle `destroy`, penghentian track kamera, pelepasan buffer canvas/decoder, pembersihan timer, dan penolakan hasil kamera/frame dari sesi yang sudah ditutup. | Resolusi, autofocus, zoom, format barcode, dan kecepatan decoding tetap. Buffer hanya dilepas saat scanner ditutup. |

Cache statis inventaris sebelumnya terutama berisiko pada proses yang hidup lama. Pada deployment PHP-FPM biasa, state PHP memang berakhir setiap request; perubahan ini tidak diklaim sebagai penghematan lintas request FPM.

## Benchmark sebelum/sesudah

Masing-masing skenario dijalankan tiga kali dalam proses PHP baru; tabel memakai median. Fixture berisi 5.000 baris per tabel (`rentals`, `product_units`, `invoices`, `rental_items`), satu produk/pelanggan, catatan 2 KiB, serta contoh payload dokumen rental 8 KiB. Variasi data nyata akan memengaruhi besarnya penghematan.

Memori di bawah adalah **tambahan peak heap PHP selama pemanggilan service**, diukur dengan `memory_reset_peak_usage()` dan `memory_get_peak_usage() - memory_get_usage()` awal. Angka tidak mencakup boot framework, persiapan fixture, seluruh memori native database, atau RSS sistem operasi. Penurunan ini bukan persentase penghematan RAM seluruh server.

| Skenario | Sebelum (MiB) | Sesudah (MiB) | Pengurangan | Waktu sebelum → sesudah (ms) | Kueri sebelum → sesudah |
| --- | ---: | ---: | ---: | ---: | ---: |
| Ringkasan rental | 18,573 | 0,327 | 98,2% | 379,45 → 7,94 | 1 → 1 |
| Stok | 11,551 | 0,356 | 96,9% | 206,71 → 5,97 | 2 → 2 |
| Ringkasan pajak | 35,556 | 0,193 | 99,5% | 163,88 → 3,48 | 1 → 1 |
| Keterlambatan | 130,412 | 0,928 | 99,3% | 303,64 → 11,58 | 2 → 3 |
| Diskon | 87,571 | 14,923 | 83,0% | 351,76 → 293,30 | 1 → 1 |
| Ketersediaan unit | 100,208 | 23,623 | 76,4% | 333,12 → 289,53 | 5 → 5 |

Semua skenario terukur lebih cepat. Lima skenario menghasilkan hash JSON identik sebelum/sesudah. Nilai pajak sama sampai ketelitian mata uang dua desimal: penjumlahan float PHP lama menghasilkan `55149.99999999633` dan `556400.0000000714`, sedangkan agregat SQLite menghasilkan `55150` dan `556400`.

Stok pada fixture hanya memerlukan dua kueri karena semua unit tanpa gudang; pengujian dengan gudang terisi memverifikasi tiga kueri tetap. Laporan keterlambatan memakai satu kueri tambahan untuk ringkasan tanpa memuat seluruh detail; dampak latensi jaringan MySQL perlu diperiksa di deployment sebenarnya. Batch komputer juga menambah pembacaan per 200 baris; tidak ada penggantian observer dengan raw update.

### Menjalankan ulang

```sh
php -d memory_limit=512M scripts/benchmark-memory.php summary 5000
php -d memory_limit=512M scripts/benchmark-memory.php stock 5000
php -d memory_limit=512M scripts/benchmark-memory.php tax 5000
php -d memory_limit=512M scripts/benchmark-memory.php late 5000
php -d memory_limit=512M scripts/benchmark-memory.php discounts 5000
php -d memory_limit=512M scripts/benchmark-memory.php occupancy 5000
```

Script memaksa SQLite `:memory:`, cache/session array, queue sync, dan mengabaikan `.env` serta cache konfigurasi/rute produksi. Migrasi dan fixture hanya dibuat dalam database sementara proses tersebut. Batas 512 MiB hanya untuk proses benchmark agar versi lama yang boros tetap dapat dibandingkan.

Argumen ketiga opsional menunjuk direktori berisi salinan service baseline. Untuk mereproduksi pembanding, salin `RentalReportService.php`, `InventoryReportService.php`, `TaxReportService.php`, dan `RentalOccupancyService.php` dari commit `6c04d51` ke direktori sementara, lalu berikan direktori itu. Jangan mengganti berkas service pada checkout aktif. `WAREHOUSE_BENCHMARK_SHOW_RESULT=1` menampilkan hasil sintetis untuk pemeriksaan angka.

## Potensi lanjutan dari audit

Temuan berikut masih berupa peluang perbaikan; belum diterapkan atau dibenchmark pada tugas ini. Urutan mempertimbangkan pertumbuhan data dan risiko perubahan, bukan bukti insiden produksi.

| Prioritas | Lokasi | Potensi RAM dan langkah berikutnya |
| --- | --- | --- |
| Tinggi | `Settings/Pages/BackupAndRestore.php:processBackup/processRestore` | Backup memuat satu tabel lengkap, mengubahnya ke array/JSON, lalu menyerahkannya ke ZIP. Restore juga memuat JSON tabel lengkap sebelum membagi insert. Berkas/media memakai beberapa buffer penuh. Perbaikan perlu export/import bertahap dengan snapshot konsisten, format backup kompatibel, serta tes round-trip dan kegagalan parsial. |
| Tinggi | `CheckLateRentals`, `SendRentalReminders`, `GenerateRecurringRentals`, `RunMonthlyDepreciation` | Sebagian pekerjaan memuat semua rental/unit beserta relasi; reminder mempertahankan beberapa koleksi sekaligus. Gunakan batch berbasis ID dan penghitung/summary kecil sambil menjaga urutan notifikasi, transaksi, audit, serta jurnal. Untuk recurrence, batasi ID sumber pada awal proses agar quotation baru tidak ikut diproses ulang. |
| Tinggi untuk kiosk | `desktop-kiosk/src/heartbeat.js`, `network.js`, `offline-queue.js` | Heartbeat memakai interval tanpa batas waktu request/pengaman request tumpang tindih. Queue membaca/parsing seluruh JSONL serta membuat salinan saat serialisasi request. Perlu single-flight, timeout/abort lifecycle, dan flush bertahap yang mempertahankan event baru saat request berlangsung. Harus diuji offline/reconnect serta command ACK. |
| Menengah | `Reports.php`, `reports.blade.php`, `FinancialReports.php` | Subtab yang disembunyikan melalui `x-show` tetap dihitung/render di server; beberapa tabel melakukan pagination setelah semua ringkasan dimuat. Mengubahnya menjadi lazy dapat menambah jeda saat berpindah tab. Ukur dahulu dan gunakan preload terbatas/pagination database bila terbukti lebih baik. |
| Menengah | `RentalReportService::durationAndConversion/revenueOverTime/depositsAndPayments`, `InventoryReportService::unitMetrics`, detail aging/pajak/GL | Sebagian keluaran memang membutuhkan banyak baris. Masih ada duplikasi koleksi model → array dan agregasi PHP. Kandidat: agregat portabel, proyeksi kolom tambahan, serta export CSV bertahap. PDF tetap memerlukan layout dokumen utuh; uji hasil dan urutannya. |
| Menengah | `RentalEditor`, kalender admin/publik, kanban, pilihan resource | Banyak pemuatan sudah dibatasi tanggal, produk, limit atau pagination; beberapa relasi rental masih mengambil seluruh kolom termasuk snapshot. Tinjau proyeksi per pemakai, jangan menerapkan global scope yang menyembunyikan kolom karena model yang sama dipakai operasi pickup/Mufise. |
| Menengah | `MufiseClient`, `MufiseController` | Foto dibatasi sekitar 4,8 MiB, tetapi base64, JSON, enkripsi, dan multipart membuat beberapa representasi di memori. Respons dokumen memakai `body()` penuh. Streaming/penyimpanan sementara perlu mempertahankan enkripsi snapshot dan retry dengan UUID/body identik. |
| Rendah–menengah | `filament/hooks/qr-scanner.blade.php`, `responsive-navigation.blade.php`, editor label | Scanner global berbeda dari scanner pickup/return. Lifecycle navigasi SPA dan listener global perlu profil browser. Editor label sudah membatasi history 60 langkah, tetapi gambar data-URL besar dapat berulang dalam snapshot; blob URL export juga perlu dilepas. |
| Rendah | `MigrateProductImagesToPublicDisk` dan command perawatan sekali jalan | Menggunakan pembacaan file penuh dan koleksi produk lengkap. Streaming file/batch menurunkan peak ketika migrasi banyak gambar, dengan dampak kecil pada request harian. |
| Perlu pengukuran deployment | `nixpacks.toml`, konfigurasi PHP-FPM `/assets/php-fpm.conf`, queue, database | Konfigurasi FPM efektif dan RAM container tidak berada dalam repo. Ambil RSS worker pada beban normal dan puncak, jumlah worker aktif, pemakaian database, serta request bersamaan sebelum menentukan concurrency. Menurunkan `memory_limit` tidak mengurangi alokasi aktual dan bisa menggagalkan request. |

Bagian yang sudah menggunakan pendekatan hemat: katalog dipaginasi 12 produk, kalender membatasi rentang/hasil, statistik dashboard memakai cache, chart rental memakai agregat database, sebagian command maintenance memakai `chunkById`, metrik unit memakai subquery agregat untuk menghindari N+1, dan polling editor Mufise memiliki pembersihan timer/listener. Pendekatan tersebut dipertahankan.

## Verifikasi

- Sebelum perubahan: `composer test:smoke` — 12 lulus, 32 assertions; `composer test` — 71 lulus, 21 gagal lama.
- Tes baru: `php artisan test tests/Feature/MemoryEfficientReportsTest.php tests/Feature/ProcessComputerBookingsMemoryTest.php` — 8 lulus, 51 assertions. Mencakup status/tanggal kosong, batas 50 detail, empat lapisan diskon, PPh 23, stok/gudang, cache scope, logistik, pajak, serta 615 booking melewati batas batch sambil memverifikasi observer.
- Tes jadwal/status terarah: `php artisan test tests/Feature/PartialReturnSchedulingTest.php tests/Feature/RentalQuotationExpiryTest.php` — 6 lulus, 78 assertions.
- `node --test tests/js/*.test.mjs` — 26 lulus; empat tes baru memeriksa pelepasan resource scanner, izin kamera yang terlambat, pergantian lensa, dan frame dari sesi lama.
- `npm run build` lulus; aset scanner dan manifest yang terlacak diperbarui. Peringatan database Browserslist lama berasal dari toolchain yang ada.
- `php -l` untuk seluruh PHP yang diubah dan `git diff --check` lulus.
- Suite lengkap: `APP_DEBUG=false composer test` — 79 lulus, 21 gagal lama, 614 assertions; nama seluruh kegagalan sama persis dengan baseline. `php -d memory_limit=256M vendor/bin/phpunit` juga menyelesaikan 100 tes dengan 19 failures + 2 errors yang sama. Mode debug bawaan pada `composer test` melewati 128 MiB saat merender halaman exception tes lama setelah penambahan tes; pengujian tanpa renderer debug selesai pada batas bawaan tersebut. Ini pengaturan sementara proses tes, tidak mengubah `.env`, `phpunit.xml`, atau batas RAM aplikasi.

Pengujian di sini memakai SQLite. Belum ada pengukuran beban MySQL produksi, RSS PHP-FPM/Electron, profil heap browser yang berjalan lama, atau pengujian kamera fisik. Pemeriksaan tersebut diperlukan untuk memastikan penghematan RAM keseluruhan dan waktu respons pada deployment sebenarnya.
