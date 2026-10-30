# Deploy Warehouse FTV tanpa perintah SSH rutin

## Temuan dari repositori

Warehouse FTV sebelumnya tidak menyediakan startup deployment untuk migrasi dan pembersihan cache. Pengaturan builder/start command produksi berada di Dokploy, sehingga penyebab pasti kebutuhan `docker service update --force` belum dapat dibuktikan dari repo. Mufise di checkout lokal memiliki Dockerfile dan startup Node tersendiri; keberhasilannya tidak membuktikan konfigurasi kedua service sama.

`optimize:clear` Laravel sudah menjalankan `config:clear`, `cache:clear`, `view:clear`, dan pembersihan cache kode lainnya. Tidak perlu menjalankan keempat perintah secara berulang.

## Startup yang tersedia

`scripts/start-container.sh` membungkus **perintah server yang sudah digunakan**. Saat container mulai, script:

1. Membuat direktori runtime yang diperlukan.
2. Menghapus cache konfigurasi, rute, event, view, komponen Filament, dan ikon.
3. Menjalankan `php artisan migrate --force --no-interaction`. Migrasi yang sudah tercatat tidak dijalankan lagi.
4. Memulai server hanya jika semua langkah berhasil; kegagalan terlihat di log container.

Script tidak membangun cache rute karena `routes/web.php` memilih rute berdasarkan `storage/installed`. Script juga tidak menjalankan `cache:clear` otomatis: cache aplikasi dapat memuat lock scheduler, pairing, dan operasi lain. Pembersihan cache kode sudah terpisah dari data cache tersebut. Jangan memasukkan migrasi ke tahap build image karena database/volume runtime belum tentu tersedia di tahap itu.

## Pemasangan sekali di Dokploy

Build Type deployment ini adalah **Nixpacks**. Repo menyediakan `nixpacks.toml` yang otomatis dibaca dari akar proyek. Konfigurasi ini mempertahankan build aset dari provider, memeriksa persyaratan PHP Composer, serta menjalankan prestart Nixpacks → persiapan Laravel → PHP-FPM/Nginx dengan template bawaan Nixpacks. Referensi implementasi: [provider PHP Nixpacks](https://github.com/railwayapp/nixpacks/blob/main/src/providers/php/mod.rs).

Log deployment yang diperiksa pada 30 Oktober 2026 menunjukkan snapshot bawaan Nixpacks menghasilkan PHP **8.4.2** dan Node **18.20.5**. Build berhenti pada pemeriksaan PHP; Node 18 juga tidak memenuhi kebutuhan Vite 7 dan `@zxing/library`. Konfigurasi sekarang mematok snapshot `ac21430f558e5714364c7e4757b8b680815bb538`, yang berisi [PHP 8.4.27](https://github.com/NixOS/nixpkgs/blob/ac21430f558e5714364c7e4757b8b680815bb538/pkgs/development/interpreters/php/default.nix) dan [Node 24.21.0](https://github.com/NixOS/nixpkgs/blob/ac21430f558e5714364c7e4757b8b680815bb538/pkgs/development/web/nodejs/v24.nix). Daftar paket setup diganti eksplisit agar Node 18/npm lama tidak ikut terpasang. `nodejs_24` menyediakan npm; overlay npm lama tidak diperlukan.

1. Sediakan perubahan pada branch/commit sumber yang dibaca Dokploy.
2. Pertahankan Build Type **Nixpacks** dan Build Path di akar proyek.
3. Jika ada `NIXPACKS_START_CMD` atau override Start Command di Dokploy, hapus override lama agar `[start]` dari repo digunakan. Jika override lama menjalankan proses tambahan, gabungkan proses tersebut terlebih dahulu; jangan menghapusnya tanpa memeriksa isinya. Variabel environment dan CLI memiliki prioritas di atas file konfigurasi.
4. Jika `NIXPACKS_CONFIG_FILE` menunjuk file lain, arahkan ke `nixpacks.toml`. Pertahankan environment database, `APP_KEY`, domain/port, dan volume yang sudah dipakai.
5. Build dan deploy baru. Pastikan build log menampilkan startup dengan `scripts/start-container.sh`, dan log runtime menampilkan `[warehouse]` sampai server mulai.

Pada build baru, bagian setup harus mencantumkan `php84`, `php84Packages.composer`, dan `nodejs_24`; snapshot pada log harus `ac21430...`, bukan `e24b4c09...`. Jika tetap tampil Node 18/snapshot lama, periksa commit sumber dan override `NIXPACKS_PKGS`, `NIXPACKS_INSTALL_CMD`, atau konfigurasi build lainnya. Bila perlu jalankan satu build dengan `NIXPACKS_NO_CACHE=1`, lalu hapus variabel itu setelah build berhasil. Menghapus cache tanpa memperbarui snapshot tidak memperbarui PHP.

Tidak perlu menambahkan `NIXPACKS_START_CMD` bila tidak ada override. Konfigurasi repo memakai path aset standar `/assets`, prestart Node, PHP-FPM, dan Nginx yang disediakan provider PHP Nixpacks. Jika deployment memakai template Nginx custom dari luar repo, sesuaikan path/template tersebut pada `[start]` sebelum deploy. Repo ini saat ini tidak memiliki `nginx.conf` atau `nginx.template.conf` custom.

Install Composer sekarang memakai lockfile dengan `--no-dev` tanpa `--ignore-platform-reqs`. `composer check-platform-reqs --no-dev` tetap memeriksa hasil instalasi. Aset memakai `npm ci --include=dev` agar Vite tersedia walaupun environment build bernilai production. Jika pemeriksaan gagal, perbarui builder/runtime sesuai [panduan keamanan runtime](php-security-update-dokploy.md), bukan menghapus pemeriksaannya.

### Builder lain atau start command khusus

Ubah perintah startup menjadi pola berikut, dengan mengganti `PERINTAH_SERVER_LAMA` dengan perintah yang sebenarnya:

```sh
sh scripts/start-container.sh sh -c 'PERINTAH_SERVER_LAMA'
```

Untuk perintah sederhana, teruskan executable dan argumennya langsung supaya sinyal stop diterima server melalui `exec`. Untuk perintah gabungan, gunakan `sh -c` seperti di atas dan pertahankan pengelolaan proses server dari builder. Jangan mengganti server produksi dengan `php artisan serve`.

- **Nixpacks custom:** bila memang perlu override, salin seluruh start command yang dihasilkan builder sebagai isi `PERINTAH_SERVER_LAMA`; pertahankan prestart, konfigurasi PHP-FPM, dan Nginx beserta path asetnya. Mengganti start command hanya dengan script tanpa argumen akan ditolak.
- **Dockerfile:** bungkus CMD server yang sudah ada dengan `sh scripts/start-container.sh`, atau panggil script dari entrypoint setelah persiapan permission. Script harus masuk ke image dan dijalankan dari direktori aplikasi.
- **Railpack/Compose:** bungkus start command service web yang sudah ada dengan pola yang sama; pertahankan entrypoint/permission/port dari konfigurasi aktif.

Dokumentasi builder: [Dokploy Build Type](https://docs.dokploy.com/docs/core/applications/build-type), [PHP Nixpacks](https://nixpacks.com/docs/providers/php). Jangan memilih build type baru hanya untuk menjalankan script ini. Tetap penuhi versi PHP pada [panduan keamanan runtime](php-security-update-dokploy.md).

Gunakan **satu replica web** untuk pola migrasi startup ini. Untuk beberapa replica atau deployment bersamaan, jalankan migrasi melalui satu release job terlebih dahulu, lalu set `WAREHOUSE_RUN_MIGRATIONS=0` pada replica web dan worker. Migrasi yang menghapus/mengubah struktur secara tidak kompatibel tetap perlu direncanakan; rollback image tidak membalik database. Simpan backup sebelum rilis yang mengubah skema.

Di **Advanced → Swarm Settings → Update Config**, jika deployment ini memang memerlukan `stop-first`, simpan `Order = stop-first` di Dokploy agar tidak perlu mengubahnya melalui SSH setiap rilis. Ini memberi jeda layanan untuk satu replica. Kebutuhan tersebut harus diperiksa dari log task/update service; `--force` tidak membangun image baru dan bukan perbaikan cache Laravel. Dokploy mendokumentasikan pengaturan ini pada [Advanced](https://docs.dokploy.com/docs/core/applications/advanced).

## Volume dan verifikasi

- Pertahankan unggahan dan `storage/installed` pada storage persisten, serta database persisten jika memakai SQLite. Jangan membuat marker instalasi secara membabi buta pada database baru.
- Jangan mount seluruh `/app`, source code, `vendor`, `public/build`, atau `bootstrap/cache` ke volume lama: volume dapat menutupi file image baru. Periksa isi dan cadangkan data sebelum memindahkan mount yang sudah ada.
- Pengguna yang menjalankan script serta PHP web harus dapat menulis `storage` dan `bootstrap/cache`. Jangan menggunakan permission `777` sebagai solusi umum.
- Service queue/scheduler terpisah harus menerima image baru juga. Jangan menjalankan migrasi pada setiap worker; gunakan `WAREHOUSE_RUN_MIGRATIONS=0` jika worker memakai wrapper ini.

Deploy dari Dokploy, lalu lihat log `[warehouse]` sampai `Ready; starting server...`. Pastikan image/commit baru aktif, `/up` sehat, storefront dan `/admin` bisa dibuka, serta tidak diarahkan kembali ke setup. Jika update service gagal sebelum startup, periksa log task, health check, port, dan mount; script Laravel tidak memperbaiki kegagalan orkestrasi.

Perubahan ini baru terpasang setelah commit sumber dibangun/deploy oleh Dokploy. Pengaturan produksi, override start command, log Swarm, dan keberhasilan deployment belum diverifikasi dari komputer lokal.

## Pengujian lokal

```sh
sh -n scripts/start-container.sh
sh tests/deploy/start-container-test.sh
composer test:smoke
```

Tes wrapper memakai perintah tiruan dan tidak mengakses database produksi. Tes memeriksa urutan startup, penghentian saat persiapan/migrasi gagal, penerusan hasil server, dan opsi melewati migrasi. Verifikasi deployment nyata tetap memerlukan log/container Dokploy.
