# Admin PWA & Web Push Setup

PWA terinstallable + push notifications **khusus untuk panel `/admin`**.
Frontend storefront tetap pakai PWA lama (`/manifest.json`, `/sw.js`) — tidak diubah.

## Yang sudah dibuat

- `GET /admin/manifest.webmanifest` — manifest dinamis (scope/start_url = `/admin`, icon dari Settings)
- `GET /admin/sw.js` — service worker khusus admin (push + notification click)
- `POST /admin/push/subscribe` / `unsubscribe` — endpoint subscription (auth admin)
- `POST /admin/push/test` — kirim test push ke user yg login
- Tabel `push_subscriptions` (`user_id`, `endpoint`, `p256dh`, `auth`, …)
- Listener `App\Listeners\SendWebPushOnNotification` — auto-push setiap Laravel Notification yang punya channel `database`
- Settings page **Settings → Admin App & Push**: master switch, app name, per-jenis-notifikasi toggle, tombol test push
- Settings page **Settings → Appearance**: upload icon (dipakai sebagai favicon tab browser + icon home screen PWA)
- Install banner otomatis di `/admin` saat dibuka dari HP (Android Chrome / iOS Safari). Hilang sendiri kalau sudah berjalan sebagai aplikasi terinstall.

## Setup pertama kali (production)

### 1. Generate VAPID keys

VAPID keys = identitas server untuk Web Push. Wajib di-generate sekali dan **disimpan permanen** (jangan diregenerate setiap deploy — semua device terdaftar akan invalid).

**Cara otomatis (paling mudah, untuk hosting yang `.env`-nya persisten):**

```bash
php artisan push:generate-vapid
php artisan config:clear
```

Command akan menulis 3 baris ke `.env`:

```
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:admin@example.com
```

Ganti `mailto:admin@example.com` dengan email admin sebenarnya.

### 2. Cara MANUAL (Dokploy / Docker / hosting dengan env terpisah)

Kalau pakai Dokploy, `.env` di dalam container sering tidak persisten — env diatur lewat dashboard Dokploy. Cara:

1. **Generate keys di tempat lain** (laptop lokal, terminal staging, atau Dokploy shell sementara):
   ```bash
   php artisan push:generate-vapid --show
   ```
   Flag `--show` hanya mencetak ke layar, tidak menulis `.env`.

2. **Copy 3 baris yang muncul.**

3. **Buka Dokploy → Aplikasi → Environment Variables**, tambahkan 3 variabel:
   - `VAPID_PUBLIC_KEY` = (dari output)
   - `VAPID_PRIVATE_KEY` = (dari output)
   - `VAPID_SUBJECT` = `mailto:emailadmin@domain.com`

4. **Redeploy / restart** container supaya env baru terbaca.

5. **Cek status di admin**: buka `/admin/settings/admin-pwa-settings`. Harus tertulis:
   > ✓ Configured

### Alternatif tanpa command (kalau artisan tidak bisa dijalankan)

VAPID keys bisa di-generate dari mana saja. Contoh node.js sekali pakai:

```bash
npx web-push generate-vapid-keys
```

Atau online: https://vapidkeys.com (jangan untuk production secret — generate di mesin sendiri).

Hasil format ECDSA P-256:
- Public key: base64url string ~87 char (diawali huruf "B")
- Private key: base64url string ~43 char

Tempel ke env var seperti di langkah 3.

### 3. Verifikasi

- Buka `/admin` dari browser HP (Android Chrome atau iOS Safari).
- **Android Chrome**: banner "Install Warehouse FTV" muncul. Tap Install → app jadi icon di home screen.
- **iOS Safari**: banner muncul dengan instruksi "Share → Add to Home Screen". iOS **tidak mendukung install otomatis** — wajib lewat Share menu.
- Buka aplikasi dari home screen. Banner install tidak muncul lagi.
- Pertama kali buka sebagai aplikasi terinstall, akan ada prompt "Allow notifications?". Izinkan.
- Di admin desktop: `Settings → Admin App & Push → Kirim Test Push`. Notifikasi muncul di HP.

## Catatan iOS

- Push notification di iOS **HANYA bekerja kalau PWA sudah diinstall ke home screen**. Buka di Safari saja → push tidak akan jalan. Ini batasan Apple, bukan bug.
- Minimum iOS 16.4 / iPadOS 16.4 (Maret 2023).
- iOS PWA tidak punya cara cek update otomatis — kalau ganti icon/nama di Settings, user di iOS harus uninstall + reinstall dari home screen.

## Catatan Android

- Android Chrome / Edge / Samsung Internet: penuh support, push jalan walau aplikasi tertutup.
- Firefox Android: push jalan, tapi install banner tidak muncul (pakai menu browser "Install app").

## Troubleshooting

- **Banner install tidak muncul di Android**: Pastikan https (bukan http), manifest terbaca (`/admin/manifest.webmanifest` return JSON), SW terdaftar (`/admin/sw.js` return JS dengan header `Service-Worker-Allowed: /admin`). Chrome DevTools → Application → Manifest / Service Workers.
- **Push tidak masuk**: Cek `Settings → Admin App & Push` → VAPID Configured = ✓, master switch ON, jenis notifikasi yang diharapkan ON. Pastikan user yg dimaksud sudah punya row di tabel `push_subscriptions` (dibuat otomatis saat allow notifications di HP-nya).
- **"VAPID belum dikonfigurasi"**: env belum ter-load. Jalankan `php artisan config:clear`. Di Dokploy: pastikan env var ter-set di dashboard dan container sudah restart.
- **Banner muncul terus padahal sudah install**: pasti dibuka dari URL yang sama. Banner cek `display-mode: standalone`. Kalau iOS, cek `navigator.standalone`. Banner di-dismiss 7 hari kalau user tap "Nanti".

## File yang ditambahkan / diubah

```
app/Console/Commands/GenerateVapidKeysCommand.php   (baru)
app/Http/Controllers/AdminPwaController.php          (baru)
app/Listeners/SendWebPushOnNotification.php          (baru)
app/Models/PushSubscription.php                      (baru)
app/Services/WebPushService.php                      (baru)
app/Filament/Clusters/Settings/Pages/AdminPwaSettings.php (baru)
app/Filament/Clusters/Settings/Pages/AppearanceSettings.php (icon upload)
app/Providers/AppServiceProvider.php                 (listener registration)
app/Providers/Filament/AdminPanelProvider.php        (render hook)
config/webpush.php                                   (baru)
database/migrations/2026_05_27_120000_create_push_subscriptions_table.php (baru)
resources/views/admin-pwa/service-worker.blade.php   (baru)
resources/views/filament/hooks/admin-pwa.blade.php   (baru)
resources/views/filament/clusters/settings/pages/admin-pwa-settings.blade.php (baru)
routes/web.php                                       (4 rute admin PWA)
composer.json / composer.lock                        (minishlink/web-push)
```
