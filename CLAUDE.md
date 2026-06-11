# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is **Gearent** (also referenced as "Zewalo" in older planning docs), a rental/warehouse management SaaS built on Laravel 12 + Filament 4. It serves two distinct audiences from one codebase:

- **Admin panel** at `/admin` — built with Filament 4 (Resources, Pages, Clusters, Widgets). Handles rentals, inventory, customers, finance, CMS.
- **Customer storefront** (frontend) — traditional Blade views under `resources/views/frontend/**` with controllers in `app/Http/Controllers/` (not the `Frontend` subfolder alone; most are at the top level).

The app has a first-run **installation wizard**. `routes/web.php` gates *all* routes behind `File::exists(storage_path('installed'))` — if that marker file is missing, every request is redirected to `/setup`. When working on route/controller changes locally, ensure the `storage/installed` file exists or your routes will 404.

## Common Commands

```bash
# Full dev stack (server + queue + pail logs + vite) via concurrently
composer dev

# Individual services
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
php artisan pail          # tail logs
npm run dev               # vite
npm run build

# Tests
composer test             # clears config then runs `php artisan test`
php artisan test --filter=SomeTest
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
# Tests use sqlite :memory: (see phpunit.xml). There's also phpunit.mysql.xml for MySQL runs.

# Lint / format
vendor/bin/pint

# First-time setup (copies .env, key:generate, migrate, npm install, build)
composer setup

# Versioning (see CHANGELOG.md footer)
php artisan make:version
php artisan make:version #VERSION
php artisan make:version --message="msg1|msg2|msg3"

# Scheduled commands defined in routes/console.php — not cron-triggered externally:
php artisan rentals:check-late          # every 5 min
php artisan app:send-rental-reminders   # daily 09:00
php artisan finance:run-depreciation    # last day of month 23:00
php artisan computer-bookings:process   # every 5 min (no-show/active/completed transitions)
```

Default DB is **SQLite** (`.env.example`). Session/queue/cache all use `database` driver by default.

## Architecture

### Product / inventory hierarchy

`Product` → `ProductVariation` (optional) → `ProductUnit` (serial-tracked physical unit) → `UnitKit` (accessory components attached to a unit).

- A product has *either* no variations (bare `product_id` composite key) *or* one-or-more variations (composite key `{product_id}:{variation_id}`). This distinction drives the `composite_id` format throughout the rental editor and all availability queries.
- `ProductUnit` is the schedulable atom — availability checks, `RentalItem`, and calendar events all operate at this level.
- `UnitKit` records accessory components (e.g. "battery pack A1") hanging off a `ProductUnit`. When `track_by_serial = true`, `UnitKitObserver::saving` calls `KitUnitLinker` to auto-resolve (or create) a shadow `ProductUnit` for the kit slot, stored as `linked_unit_id`. This keeps component serials independently trackable.
- `RentalValidationService` also enforces operational schedule (JSON setting `operational_schedule`) and holidays (JSON setting `holidays`). It is used for both rental and computer-booking date validation.
- `Product.buffer_time` (days) pads the post-return unavailability window to prevent back-to-back bookings without maintenance time.

### The Rental domain is the center of gravity

Everything revolves around the `Rental` model and its lifecycle: `quotation` → `confirmed` → `active` → `completed` / `partial_return` / `cancelled`. Additional statuses: `late_pickup` (confirmed, past start, not picked up) and `late_return` (active, past end date). Full constant list on `Rental`: `STATUS_QUOTATION`, `STATUS_CONFIRMED`, `STATUS_ACTIVE`, `STATUS_COMPLETED`, `STATUS_CANCELLED`, `STATUS_LATE_PICKUP`, `STATUS_LATE_RETURN`, `STATUS_PARTIAL_RETURN`. Critical pieces:

- `app/Models/Rental.php` + `RentalItem` + `RentalItemKit` — rental with per-unit breakdown, including kit-level serial tracking.
- `app/Services/RentalValidationService.php` — double-booking prevention, product unit conflict resolution, date overlap checks. Any code that creates/edits rentals or checks availability should go through this service.
- `app/Observers/RentalObserver.php` — wired up in `AppServiceProvider::boot()`. Side-effects on rental state changes live here.
- `app/Http/Controllers/CheckoutController.php` (customer flow) and `app/Filament/Resources/Rentals/**` (admin flow) are the two entry points that create rentals; both must stay in sync on validation rules.
- `CatalogController::checkAvailability` powers live unit availability on the storefront — changes to availability logic must be mirrored in both the frontend calendar and this endpoint.

### Rental editor (Create / Edit) — custom Livewire, NOT Filament default

`/admin/rentals/create` dan `/admin/rentals/{id}/edit` **tidak memakai Filament form builder** seperti resource lain. Keduanya merender view `filament.rentals.editor` (Blade `resources/views/filament/rentals/editor.blade.php`) yang membungkus satu komponen Livewire: [`App\Livewire\Admin\RentalEditor`](app/Livewire/Admin/RentalEditor.php) dengan view [`resources/views/livewire/admin/rental-editor.blade.php`](resources/views/livewire/admin/rental-editor.blade.php) (~2000 baris, custom UI desktop + mobile).

- `CreateRental` page passes `record = null`; `EditRental` page passes existing `Rental` model. Komponen Livewire pakai `!$record || !$record->exists` untuk membedakan mode (tombol "Create Rental" / "Buat Rental" vs "Save Changes" / "Simpan"). Logika save: `Rental::create()` saat baru, `fill() + saveQuietly()` saat update.
- Items disimpan di array `$items` Livewire (state in-memory), **grouped per product-variation** (bukan 1 RentalItem = 1 baris UI). Setiap row punya `composite_id` (`{product_id}` atau `{product_id}:{variation_id}`), `quantity`, dan `unit_ids[]` (array serial unit IDs). DB-level `RentalItem` tetap satu-per-unit; mapping antara state Livewire ↔ DB ada di `RentalForm::syncRentalItems()` dan `RentalForm::groupItemsForForm()`.
- **`ItemsRelationManager` masih ada di codebase tapi TIDAK dirender** di halaman edit baru ini. Pengelolaan item sepenuhnya lewat Livewire editor. Jangan tambah action di RelationManager untuk fitur baru — tambahkan di `RentalEditor` + blade view.
- **Stock-aware tapi tidak menghalangi**: catalog popup (modal desktop + sheet mobile) tetap mengizinkan add saat stok 0 atau kurang dari qty diminta — row dibuat dengan slot `unit_ids` parsial / kosong, badge kuning "+N kosong" muncul di UI, dan user diarahkan ke fitur Transfer (lihat di bawah). Tombol `+` di catalog tidak pernah disabled.
- **Catalog desktop modal sinkron 2-arah**: tiap card produk menampilkan stepper `[− N +]` bila produk sudah ada di tabel, atau tombol `+` saja jika belum. Map qty dibangun dari `$items` tiap render sehingga catalog selalu cerminkan tabel rental. `decrementByComposite()` menghapus row saat qty turun ke 0.

### Cross-rental unit Move / Swap

Fitur untuk memindahkan / menukar / menarik `RentalItem` antar rental yang berbeda — solusi untuk kasus konflik booking: unit X sudah ter-booking di Rental A, lalu Rental B juga butuh unit X di periode overlap. Daripada "menolak karena stok 0", admin bisa **MOVE** unit dari A ke B, **SWAP** (X di A ↔ Y di B), atau **PULL** (tarik unit dari A untuk mengisi slot kosong di B yang sedang diedit).

- [`app/Services/RentalItemTransferService.php`](app/Services/RentalItemTransferService.php) — service inti dengan dua method publik: `move(RentalItem $sourceItem, Rental $targetRental)` dan `swap(RentalItem $itemA, RentalItem $itemB)`. Semua operasi dalam DB transaction. (Mode **PULL** di UI = `move()` terbalik: `move($itemDariRentalLain, $this->record)`.)
- **MOVE = transfer assignment unit, BUKAN pindah baris.** Yang dipindah adalah `product_unit_id`-nya, bukan `rental_id`:
  - **Rental sumber TETAP punya line produknya.** Source `RentalItem` di-null-kan `product_unit_id`-nya → jadi **ghost slot** (slot kosong). Quantity di sumber tidak berubah, hanya jumlah unit ter-assign yang turun. Produk **tidak pernah dihapus** dari tabel items. `product_id`/`product_variation_id` dipertahankan agar baris tetap merender produk yang sama.
  - **Rental tujuan**: kalau sudah ada ghost slot yang cocok (`product_unit_id` null, `product_id`/`variation` sama) → slot itu **diisi** (quantity TIDAK bertambah — ini kasus PULL: slot kosong "+N kosong" yang sudah dibuat user). Kalau tidak ada ghost slot cocok → buat baris baru = penambahan stok asli (quantity +1). Logika di `RentalItemTransferService::findGhostSlot()`.
  - Ini menjadikan MOVE simetris dengan SWAP (yang juga tukar `product_unit_id`, kedua baris tetap di rentalnya).
- **Guard status**: kedua sisi rental harus berstatus `QUOTATION`, `CONFIRMED`, atau `LATE_PICKUP`. Status `ACTIVE`/`LATE_RETURN`/`PARTIAL_RETURN`/`COMPLETED`/`CANCELLED` ditolak (lawan rental "belum dipickup" only). Konstanta `RentalItemTransferService::TRANSFERABLE_STATUSES`.
- **Recalc total**: tidak dilakukan manual di service. `RentalItem::$touches = ['rental']` otomatis trigger `RentalObserver::updated` saat item disimpan — observer yang handle subtotal/discount/tax/total lengkap. Karena MOVE sekarang menyimpan satu `RentalItem` di **masing-masing** rental (source jadi ghost, target dapat unit), kedua rental ter-touch otomatis — **tidak perlu** `$sourceRental->touch()` manual lagi (catatan: ghost slot sumber tetap punya subtotal `daily_rate × days`, jadi nilai sumber tidak berubah; sumber "masih memesan" produk, hanya butuh assign unit baru).
- **Konflik**: setelah operasi, service cek `checkAvailability()` di rental tujuan; jika muncul konflik baru, **seluruh DB transaction di-rollback** (`RuntimeException`).
- **Hooks otomatis**: saat `product_unit_id` berubah, `RentalItem::updated` hook auto-delete kit lama & attach kit baru; `RentalItem::saved` hook auto-relink parent/child kit. **Penting**: `attachKitsFromUnit()` sekarang early-return bila `productUnit` null (ghost slot tidak punya unit untuk diambil kitnya), dan reverse-check di `saved` hook di-skip saat `product_unit_id` null (kalau tidak, `where('unit_id', null)` jadi `whereNull` dan salah match).
- **Audit**: tidak ada activity log package. Service append catatan ke kolom `notes` kedua rental via `updateQuietly()` (agar tidak re-trigger observer), format `[YYYY-MM-DD HH:mm] MOVE/SWAP ... oleh {email}`. Plus `Log::info('RentalItem MOVE/SWAP', [...])`.
- **UI** di `RentalEditor`:
  - Method publik: `openMoveModal($unitId)`, `openSwapModal($unitId)` (per-serial), `openTransferForRow($itemKey, $mode)` (per row group — auto-pick unit jika row hanya punya 1 serial), `openPullModal($itemKey)` (tarik unit dari rental lain untuk isi slot kosong row ini).
  - Sebelum modal dibuka, `beginTransfer()` **auto-save** editor state via `persistInline()` agar source RentalItem benar-benar ada di DB. Setelah `confirmTransfer()` sukses, `redirect()` ke URL edit yang sama untuk reload state.
  - Tombol akses: (a) icon "transfer" (dua panah berputar) di row item desktop & mobile → dropdown "Move ke rental lain" / "Swap dengan rental lain"; (b) link kecil **Move · Swap** di tiap slot unit yang sudah ter-assign di Unit Modal. Hanya muncul kalau `canTransfer` true (rental berstatus eligible + record sudah exist).
  - Modal transfer (di blade view) menampilkan: picker unit (kalau dibuka dari row dengan >1 unit), select rental tujuan (filtered ke status eligible), dan select unit lawan (mode swap saja).

### Calendar / schedule views

There are **multiple** calendar surfaces — do not confuse them:

- `app/Filament/Pages/Schedule.php` and `GlobalProductSchedule.php` — admin schedule pages (by order / by product / timeline).
- `app/Filament/Pages/RentalCalendar.php` + `app/Filament/Widgets/RentalCalendarWidget.php` — saade/filament-fullcalendar based.
- `app/Http/Controllers/Frontend/ScheduleController.php` + `resources/views/frontend/schedule/index.blade.php` — public storefront calendar (`/schedule` and `/schedule/events`).
- Per-product availability calendar on the catalog detail page.

The `$colorMap` for rental statuses must stay consistent across these surfaces. `calendarfront.md` has design notes for the frontend calendar.

### Rental operations: Pickup and Return

Two custom Filament pages handle the physical handoff:

- `app/Filament/Resources/Rentals/Pages/PickupOperation.php` — marks rental `active`, records which items and kit accessories were handed out, optionally creates a `Delivery` record for outgoing items.
- `app/Filament/Resources/Rentals/Pages/ProcessReturn.php` — marks rental `completed` or `partial_return`, checks each item/kit back in, creates a return `Delivery`.

Both pages render custom Blade views (not Filament form pages) and implement `HasTable` for the items checklist. `Delivery` + `DeliveryItem` models track the logistics record for both directions.

### Unit Scanner + QR/Barcode label system (Pickup & Return)

The scan button on both operation pages ("Scan unit to hand over" / "Scan returned unit") opens a **real camera scanner** that decodes a unit/kit's QR or Code128 barcode and checks the matching item — identical effect to the manual Check button. Recreated from a React design handoff as an Alpine component (the design source lives in the Claude design-extract bundle under `scanner/`, not in the repo). Refer to this whole feature as the **"unit scanner"** (or "scanner popup" / "QR-barcode label system").

- **Closed-system code contract** — [`app/Services/UnitCodeService.php`](app/Services/UnitCodeService.php): every label encodes the string `PREFIX:<serial>` where `PREFIX` = first 4 alphanumeric chars of the company name (`Setting::get('site_name')`, uppercased; falls back to `GEAR`). `decode()` strips the prefix and returns the serial, or `null` for foreign/public codes (scanner ignores them). QR **and** barcode encode the *same* string so reprints are byte-identical. No URLs — a phone camera just shows plain text, nothing opens.
- **Label image generation** — [`app/Services/LabelImageService.php`](app/Services/LabelImageService.php): `png(string $serial, 'qr'|'barcode'): string` returns raw PNG bytes with the serial stamped beneath the code. **Pure GD** (QR drawn by hand from BaconQrCode's `Encoder::encode()->getMatrix()`; barcode via `picqer/php-barcode-generator`'s `BarcodeGeneratorPNG`) — deliberately avoids the imagick extension that `simple-qrcode`'s PNG backend would require. Needs `ext-gd` (present on the server, **absent on the local XAMPP CLI** so it can't be exercised locally).
- **Label download** — route `GET /admin/unit-label/{serial}/{type}` (`admin.unit-label`, `auth`-gated, inside the installed-gate `else` branch in `routes/web.php`) streams the PNG as a download. Exposed via a **Label** action on the product-unit table ([`UnitsRelationManager`](app/Filament/Resources/Products/RelationManagers/UnitsRelationManager.php) → `recordActions`) whose modal ([`resources/views/filament/resources/products/unit-label-modal.blade.php`](resources/views/filament/resources/products/unit-label-modal.blade.php)) lists the parent unit + each serial-bearing kit, each with `[QR]` / `[Barcode]` download links.
- **Kit "auto-scan with parent"** — `unit_kits.auto_scan_with_parent` (boolean, migration `2026_06_07_000000…`, on `UnitKit` `$fillable`+cast, toggle in the `kits` Repeater). When ON the kit is **hidden from the scan list** and **auto-checked when its parent unit is scanned** (for small accessories that can't hold a sticker). The scanner has a runtime **"Accessories" switch** (default ON) gating whether parent scans cascade to these kits.
- **Backend** (mirrored on both `PickupOperation` + `ProcessReturn`): `scannableList(): array` — scannable delivery items (excludes `auto_scan_with_parent` kits), each `{id, name, serial, type:'unit'|'kit', checked}`. `scanByCode(string $raw, bool $cascade = true, bool $manual = false): array` — decodes (or, with `$manual`, matches a raw serial/name), finds the `DeliveryItem` (unit-serial first, then kit-serial), reuses existing **`quickCheck()`** to check it (keeps the condition-sync + pickup availability guard), cascades to the unit's auto-scan kits, and returns `{status: ok|already|notfound|foreign|unavailable, label, checked:[…]}`. `scanNext()` (the old placeholder) is kept for the "Mark all" affordance.
- **Frontend** — Alpine component [`resources/js/unit-scanner.js`](resources/js/unit-scanner.js) (imports `@zxing/browser`, registered on `alpine:init` against **Filament's** Alpine — NOT bundled via the storefront `app.js`; it's a separate Vite input loaded with `@vite` in the partial). It runs the 7-phase permission state machine (`prompt → requesting → live | denied | blocked | nocamera | manual`), `desktop` modal vs `mobile` full-screen sheet (chosen by `matchMedia('(max-width:680px)')`; mobile shows a pre-camera list page first), real decode via `decodeFromStream`, torch, debounce, client-side prefix ignore, then calls `$wire.scanByCode()` and refreshes via `$wire.scannableList()`. Markup + ported CSS in [`partials/scanner-popup.blade.php`](resources/views/filament/resources/rentals/pages/partials/scanner-popup.blade.php) + [`partials/scanner-screens.blade.php`](resources/views/filament/resources/rentals/pages/partials/scanner-screens.blade.php); CSS is scoped to `.scn-*` with light defaults and `.dark` overrides (camera feed stays dark in both themes). The operation blades' inline `$icon()` helper gained `keyboard/lock/cameraOff/zap/spinner` glyphs; scan buttons now `@dispatch('open-unit-scanner')` instead of `wire:click="scanNext"`. The simulated/demo camera from the prototype was **removed** — the permission screens are the real fallback.

### Bluetooth Label Printer (LuckPrinter) + Print Label page

A **"Print Label"** admin page prints labels directly from the browser to **Luck Jingle** Bluetooth label printers (seri L10/L12/L13/L15/C16/MPL11, 96 dots / 12 mm + a few wider models) via **Web Bluetooth** — no Android app, no driver, no print server. This is the on-device counterpart to the server-rendered PNG download in the [unit scanner / label system](#unit-scanner--qrbarcode-label-system-pickup--return); both encode the **same closed-system payload** so printed labels stay scannable.

- **The driver + editor** — `public/vendor/luckprinter/**`: a standalone, **zero-dependency ES-module** bundle (reverse-engineered from the "Luck Jingle" app, GATT service `0xFF00` + ESC/POS `GS v 0` raster with credit flow-control). Public transport API in `/vendor/luckprinter/luckprinter.js` (`connectLuckPrinter`, `printRaster`/`buildRaster`, `LuckPrinter` EventTarget). Canvas composition in `label.js` (`createLabelCanvas`/`drawText`/`drawQR`/`drawBarcode`/`drawImage`); BLE transport in `driver.js`; model registry in `devices.js`; pure-JS 1D barcode (CODE128/EAN-13) in `vendor/barcode.js`. The real UI is **`editor.html` + `editor-app.js`** — a full **WYSIWYG label designer** (free canvas: add/drag/resize/rotate text·QR·barcode·image·line·rect, per-element property panel, templates, undo/redo, save/load/export-import JSON, paper-size + orientation + zoom, "Pratinjau Cetak" of the rotated head bitmap). All coordinates in **dots** (203 dpi = 8 dots/mm); the design is landscape and rotated 90° at print time so width = printer head. `test.html` is an older single-label reference UI. **Everything is loaded as raw `<script type="module">`, NOT via Vite** — deliberately zero-build (do not move into `resources/js/`). Folder was relocated `public/luckprinter/` → `public/vendor/luckprinter/`; keep all import paths `/vendor/luckprinter/...`.
- **The page = an iframe, not a port** — [`app/Filament/Pages/LabelPrinter.php`](app/Filament/Pages/LabelPrinter.php) (slug `label-printer`, route `filament.admin.pages.label-printer`, nav **Inventory**) + view [`resources/views/filament/pages/label-printer.blade.php`](resources/views/filament/pages/label-printer.blade.php) just embed `editor.html` in a **same-origin `<iframe allow="bluetooth">`** so the editor runs exactly 1:1 (Web Bluetooth is allowed in same-origin frames). `editor-app.js` is vanilla JS bound to fixed DOM ids + `DOMContentLoaded` with its own global CSS — do **not** try to port it into Alpine/Filament markup; edit it in place. `mount()` builds `editorUrl = /vendor/luckprinter/editor.html?dataUrl=<units-feed>&unit=…/units=…`.
- **Import from system** — the editor can pull unit data so QR/Barcode aren't typed by hand. Host passes `?dataUrl=` (relative path to route **`admin.label-printer.units`**, a closure in `routes/web.php` inside the installed-gate `else` branch, `auth`-gated). The iframe `fetch`es it **same-origin with the session cookie**; it returns `{data:[{serial,name,payload,type:'unit'|'kit'}]}` filtered by `?q=` (search) or `?ids=` (explicit), where **`payload = UnitCodeService::encode($serial)`** (`PREFIX:serial`). In `editor-app.js`: a **"Data Sistem"** panel button + a **"📥 Ambil dari sistem"** button inside the QR and Barcode property panels open a search picker (`openSystemPicker`); picking sets the selected element's `data` to the payload (`applyUnitToElement`) or inserts a full unit label (`insertUnitLabel`). The whole feature self-disables when `dataUrl` is absent (standalone `editor.html` still works). **Never put a raw serial in QR/barcode `data` — always the encoded payload** so it reads on the Pickup/Return scanner.
- **Deep-link entry points** — `?unit={id}` (single) / `?units=1,2,3` (bulk) make `editor-app.js` fetch those ids and prefill a unit label on open. Sources: (a) the **Label** modal on the product-unit table ([`unit-label-modal.blade.php`](resources/views/filament/resources/products/unit-label-modal.blade.php)) — a **"Print via Bluetooth"** button → `LabelPrinter::getUrl(['unit' => $record->id])` (the old QR/Barcode PNG download links stay, for paper printers/archive); (b) a **"Print Labels"** bulk action on [`UnitsRelationManager`](app/Filament/Resources/Products/RelationManagers/UnitsRelationManager.php) → `LabelPrinter::getUrl(['units' => $ids])`.
- **Constraints** — Web Bluetooth needs **Chrome/Edge** on Android/Windows/Mac and **HTTPS or localhost**; it does **not** exist on iOS/Safari (the editor shows a banner and disables Connect, never errors). `URL::forceScheme('https')` is on, but plain-HTTP local XAMPP still can't connect — test over `https://`/`localhost`. Rendering is client-side canvas (unlike server-GD `LabelImageService`), so the designer works on the local CLI-less environment; only the actual BLE print needs hardware.

### Promotions and discounts

`PromotionService::calculatePromotions()` stacks three independent discount layers in order: (1) `DailyDiscount` — "rent N days pay M" type; (2) `DatePromotion` — special-date percentage discount applied to subtotal after daily discount; (3) manual `Discount` code. The admin UI for creating these lives at `app/Filament/Pages/Promotions.php`. All three discount amounts are stored separately on the `Rental` row (`daily_discount_amount`, `date_promotion_amount`, `discount`/`discount_type`) so each layer is independently auditable.

### RBAC (Filament Shield)

`bezhansalleh/filament-shield` provides Filament RBAC. Admin role names referenced in code: `super_admin`, `admin`, `staff`. Customer users (storefront) don't use roles — they're gated by `customer.auth` middleware only. Policies are auto-discovered by Shield; the sole hand-written policy is `CartPolicy` (registered in `AppServiceProvider`).

### Finance module (optional / advanced mode)

Introduced in v1.5.0 and lives in `app/Filament/Clusters/Finance/**`. Can be toggled between a "simple" and "advanced" finance system (a setting). Key moving parts:

- `app/Services/JournalService.php`, `TaxService.php`, `TaxReportService.php`.
- Double-entry model: `JournalEntry` + `JournalEntryItem`, backed by `FinanceAccount` / `AccountMapping` / `CategoryMapping`.
- Observers in `app/Observers/` (`FinanceTransactionObserver`, `JournalEntryItemObserver`) enforce ledger integrity — wired in `AppServiceProvider::boot()`. Don't bypass them with raw DB writes.
- Monthly depreciation runs via the `finance:run-depreciation` command.

### Computer Booking module

Lab/computer booking system for FTV UPI workstations. **Fully separate** from the Rental domain (different tables, no Finance integration — bookings are free / internal). Lives in:

- `app/Filament/Clusters/Computers/**` — admin UI (cluster `ComputersCluster`). Resources: `ComputerRoomResource` (rooms), `ComputerResource` (computers, scoped to a room), `ComputerBookingResource`, `ComputerSlotResource` (slot has `is_night` toggle for night-shift permit banner), `MaintenanceLogResource`. Custom page `ComputerBookingCalendar` (FullCalendar widget) for visual scheduling. EditComputer page has a "Check-in Page" header action that links to the kiosk URL.
- `app/Models/{ComputerRoom, Computer, ComputerBooking, ComputerBookingSlot, ComputerMaintenanceLog}.php`. Each `Computer` auto-generates a unique `checkin_slug` (Str::random(24)) used by the kiosk page (`Computer::checkinUrl()`).
- `app/Services/ComputerValidationService.php` — collision detection (vs other bookings + maintenance windows), FUP quota (`computer_quota_hours_per_week`, `computer_quota_slots_per_day`), available-slot computation. Mirror of `RentalValidationService`. Any code creating/editing computer bookings should go through this service.
- `app/Observers/{ComputerObserver, ComputerBookingObserver}.php` — wired in `AppServiceProvider::boot()`. ComputerObserver auto-creates/closes `ComputerMaintenanceLog` rows on status flip; ComputerBookingObserver auto-generates `booking_code` (`CB-YYYYMMDD-####`).
- `app/Http/Controllers/{ComputerController, ComputerBookingController, ComputerCheckinController}.php` — storefront. Public: `/computers` (rooms list), `/computers/rooms/{room}` (computers in a room), `/computers/{computer}` (3-step booking wizard: date → multi-slot → confirm with night-shift permit checkbox), `POST /computers/{computer}/availability`. Customer-protected (`customer.auth`): `/customer/computer-bookings*`. Kiosk (public, slug-based): `/kiosk/checkin/{slug}` shows QR (encodes the same URL), today's bookings, and a check-in button. Walk-in flow: if user has no booking in the current operational slot, the controller auto-creates a booking marked `is_walk_in` upon successful check-in. Multi-slot booking submissions are merged into contiguous windows by `ComputerBookingController::mergeContiguous()` (so non-adjacent selections produce multiple booking rows).

### Kiosk Desktop App (Electron) + telemetry

The web kiosk page is wrapped by an **Electron desktop app** in `desktop-kiosk/` (separate package, gitignored `node_modules`/`dist`). The app runs full-screen on each lab computer, auto-starts via Windows HKLM Run key (NSIS installer), and posts heartbeat to the server every ~30s. The app is intentionally minimal: single `BrowserWindow` loading the existing Blade page, no UI framework in the renderer, GPU disabled (HTML/CSS only), heartbeat in main process. Idle target ~120 MB RAM / ~0% CPU.

Server-side pieces (Laravel):
- Migrasi kolom di `computers`: `last_seen_at`, `last_heartbeat_at`, `last_heartbeat_data` (JSON: `{app_version, uptime_seconds, running_apps:[]}`), `kiosk_token` (unique 64-char bearer), `kiosk_paired_at`. Tabel `kiosk_pairing_codes` untuk pairing flow.
- `app/Http/Controllers/KioskApiController.php` exposes `POST /api/kiosk/pair` (body: `{code}` 6-digit, throttled 5/min), `POST /api/kiosk/heartbeat` (Bearer auth via `kiosk.auth` middleware → `App\Http\Middleware\KioskBearerAuth`; accepts `command_acks: [{id, status, error?}]` in body, returns `commands: [{id, command}]` array of pending remote commands — see remote command queue below), `POST /api/kiosk/heartbeat-web/{slug}` (no bearer, throttled 6/min — used by Mac browser-kiosk where slug-in-URL identifies the computer; cannot transmit `running_apps` since browsers don't have OS process access; **does NOT receive remote commands** since browsers can't execute OS shutdown), and `GET /api/kiosk/update/latest.yml` + `GET /api/kiosk/update/{file}` for `electron-updater` (serves files from `storage/app/kiosk-releases/`). The `last_heartbeat_data.source` field records `'electron'` vs `'web'` — last write wins per computer.
- CSRF excluded for `api/kiosk/*` in `bootstrap/app.php`.
- `Computer::getIsOnlineAttribute()` reads `last_seen_at` against threshold setting (default 60s). `Computer::currentBookingUser()` mirrors the `ComputerCheckinController::show()` active-booking detection logic.
- Filament `EditComputer` page has a "Kiosk App" action group with **Pair / Re-pair Kiosk App** (generates 6-digit `KioskPairingCode` valid 5min, displayed via persistent notification), **Unpair Kiosk App**, **Show Kiosk Status** (modal with last seen / version / uptime / running apps list), and **Remote Shutdown** / **Remote Restart** (Windows-only, see remote command queue below). All these actions are auto-hidden when computer is unpaired (no `kiosk_token`).
- **Remote command queue** (Windows kiosk only): `kiosk_commands` table (`computer_id`, `command` enum `shutdown`/`restart`, `status` `pending`/`sent`/`acked`/`failed`, `issued_by`, `sent_at`, `acked_at`, `error`). Filament action enqueues a `pending` row → next heartbeat response includes up to 5 commands and flips them to `sent` → kiosk app acks via subsequent heartbeat's `command_acks` array → row becomes `acked`/`failed`. Latency = heartbeat interval (~30s). Commands queue while PC is offline; fire on reconnect. Logic split: server-side in `KioskApiController::heartbeat()` + `KioskCommand` model; kiosk-side in `desktop-kiosk/src/heartbeat.js` (`onCommands` callback, `pendingAcks` array re-queued on transient failure) + `desktop-kiosk/src/main.js` (`handleRemoteCommands` acks BEFORE executing so shutdown doesn't lose the ack). Server-side can ship without bumping kiosk version — old kiosks ignore unknown `commands` field; commands stack in `sent` state until kiosk auto-updates.
- `ComputersTable` shows columns: online icon, "Sedang Dipakai" (current booking user), last seen, plus "Online only" toggle filter.
- Frontend `rooms.blade.php` shows "X online" counter per room; `room-show.blade.php` shows online/offline/maintenance badge per computer + "Sedang dipakai: {nama}".
- Settings page has new section "Kiosk Desktop App" with `computer_kiosk_offline_threshold_seconds`, `computer_kiosk_heartbeat_interval_seconds`, `computer_kiosk_running_apps_whitelist` (multiline, server returns this to app each heartbeat so the app filters processes before transmitting), `computer_kiosk_latest_version` (display only).

Release flow: bump `desktop-kiosk/package.json` version → `npm run dist` → upload `*.exe` + `latest.yml` + `*.blockmap` to `storage/app/kiosk-releases/` → kiosks auto-fetch within 6h, install on next reboot.

**Mac lab computers** don't use the Electron app (would require Apple Developer Program $99/yr for signing + notarization + cross-build infra). Instead they run Chrome in `--app` / `--kiosk` mode pointing at the same `/kiosk/checkin/{slug}` URL, typically with `--user-data-dir=$HOME/.chrome-kiosk` to isolate from the user's daily Chrome profile (no conflict with multiple personal Google profiles). Heartbeat is performed by inline `<script>` in `checkin.blade.php` posting to `/api/kiosk/heartbeat-web/{slug}` every 30s. Online/offline status surfaces identically in admin + storefront; only `running_apps` detection is unavailable on Mac. Auto-start via macOS Login Items (`~/Documents/launch-kiosk.command` script). Mac is **dual-use** in practice — students check in via the kiosk page, then Cmd+Tab to Premiere/Photoshop while the timer tab stays in the background. Setup instructions in `setup_komputer.md` Bagian 3b + `readme_setup_komputer.txt`.

**Web-mode timer** (`checkin-timer.blade.php`): the timer page detects `!window.kioskBridge` (i.e. opened in a browser, not Electron) and adds `body.is-web-timer` class which: (a) replaces the floating-window CSS with a centered fullscreen-friendly card layout (timer scaled 56-96px clamp, sleep/shutdown buttons hidden since browsers can't execute OS commands), (b) shows the kiosk header bar and a yellow "Jangan tutup tab" warning banner, (c) live-updates `document.title` to `HH:MM:SS · Sesi aktif — Name` so students see the timer from the tab bar while another app is focused, (d) installs `beforeunload` warning + `pagehide` `sendBeacon` best-effort auto-logout (sends `booking_id` + `_token` as FormData to `/kiosk/checkin/{slug}/logout`) so the booking doesn't get stuck `ACTIVE` if the tab is force-closed. The Electron path is unchanged — all behavior gated on `isWebMode` flag, `loggedOut` flag prevents double-fire after successful logout.
- `app/Console/Commands/ProcessComputerBookingsCommand.php` — scheduled `everyFiveMinutes`. Transitions confirmed→no_show (no check-in past grace), confirmed→active (checked in + start passed), active→completed (end passed).
- Settings page at `app/Filament/Clusters/Settings/Pages/ComputerBookingSettings.php` writes `computer_quota_hours_per_week`, `computer_quota_slots_per_day`, `computer_no_show_grace_minutes`, `computer_tnc_text` keys to the `Setting` model.

Lifecycle: `confirmed` → (`active` after admin check-in) → `completed` / `cancelled` / `no_show`. Auto-confirm on submit (no admin approval gate).

Shares with Rental domain: `users` table (customer auth via `customer.auth` middleware), Filament admin panel, `Setting` model, frontend layouts/theme. Does **not** share rental tables or the Finance module.

### Admin PWA + Web Push (installable admin app)

`/admin` adalah PWA yang bisa di-install ke home screen Android & iOS, dengan push notifications ke device user yang sudah login admin. **Storefront frontend tetap pakai PWA lama yang terpisah** (`public/manifest.json`, `public/sw.js`) — jangan disatukan. Setup lengkap & troubleshooting di `ADMIN_PWA_SETUP.md`.

- Manifest dinamis di `GET /admin/manifest.webmanifest` (scope `/admin`, start_url `/admin`, icon dari Setting `pwa_admin_icon`). Service worker di `GET /admin/sw.js` di-serve dengan header `Service-Worker-Allowed: /admin`. Keduanya rute publik karena browser tidak mengirim session cookie saat fetch manifest/SW.
- `app/Http/Controllers/AdminPwaController.php` — manifest, sw, subscribe/unsubscribe/test push endpoints (subscribe & test pakai `middleware('auth')` standar Filament).
- `app/Services/WebPushService.php` — wrapper `minishlink/web-push`. `sendToUser(int $userId, array $payload)` mengirim ke semua subscription milik user, auto-prune endpoint 404/410.
- `app/Listeners/SendWebPushOnNotification.php` — listen `Illuminate\Notifications\Events\NotificationSent`, **hanya** untuk channel `database`, lalu mirror jadi web push. Daftar di `AppServiceProvider::boot()`. Per-kelas opt-out via setting `pwa_admin_push_block_{class_key}`; master switch `pwa_admin_push_enabled`. Karena cuma pasif via event, **tidak perlu ubah class Notification yang sudah ada** — selama `via()` me-return `'database'`, push otomatis ikut.
- VAPID keys wajib di env (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`). Generate via `php artisan push:generate-vapid` (auto-tulis `.env`) atau `--show` (cetak saja, untuk Dokploy/Docker dengan env terpisah dashboard). **Jangan regenerate setelah production live** — semua subscription jadi invalid.
- Tabel `push_subscriptions` (`user_id`, `endpoint` text, `p256dh`, `auth`, `user_agent`, `last_used_at`) dengan unique index `(user_id, endpoint)`.
- Filament render hook `panels::head.end` inject view `resources/views/filament/hooks/admin-pwa.blade.php`: `<link rel="manifest">`, apple-touch-icon, theme-color, SW register, push subscribe, install banner. Banner auto-hide kalau `display-mode: standalone` atau `navigator.standalone` (iOS) — jadi user yang sudah install tidak melihatnya. Banner dismiss disimpan di `localStorage` 7 hari. Android pakai `beforeinstallprompt`; iOS Safari tidak punya API itu — banner hanya menampilkan instruksi "Share → Add to Home Screen".
- Settings page **Settings → Admin App & Push** (`AdminPwaSettings.php`): VAPID status badge, master switch, app name/short name/bg color, 14 toggle per-jenis notifikasi, tombol header "Kirim Test Push". Icon upload ada di **Settings → Appearance** (key `pwa_admin_icon`, disimpan di `storage/app/public/pwa/`, dipakai sebagai favicon tab + manifest icon + apple-touch-icon).
- **iOS quirk**: push notification iOS HANYA bekerja setelah PWA terinstall ke home screen (iOS 16.4+). Buka di Safari saja tidak cukup. Ini batasan Apple. iOS PWA juga tidak refresh manifest setelah install — ganti icon/nama wajib uninstall + reinstall di device.

### Settings-driven runtime config

`App\Models\Setting` is a simple key/value store queried at boot in `AppServiceProvider`. It overrides:

- `app.name` (site_name)
- Mail config (mailer/host/port/from/etc. — only applied when `notification_email_enabled`)
- Document layout (`doc_*` keys auto-injected into all `pdf.*` views via a `View::composer`)
- Primary color theme (via `App\Services\ThemeService::getPrimaryColor()`) — used in **both** the Filament admin panel (`AdminPanelProvider`) and frontend layouts (`layouts.app`, `layouts.frontend`, `layouts.guest`). Keep these in sync; do not hardcode colors.

`AdminPanelProvider` also reads settings to choose `top` vs `sidebar` navigation and forces top-nav on detected mobile user agents. Test panel layout changes on both.

- **Timezone** — `AppServiceProvider::boot()` reads `app_timezone` from `Setting` and calls `date_default_timezone_set()`. Default is `Asia/Jakarta`. All Carbon/datetime operations are affected. Changed at **Settings → General**.

### Installation gate

`routes/web.php` reads `storage/installed`. `SetupController` (`/setup/step1` … `/setup/step6`) creates that marker. When cloning fresh or after `storage:link`, ensure this file exists or the app will think it's uninstalled.

### CMS (LaraZeus Sky)

Posts, pages, navigation, tags are provided by the `lara-zeus/sky` plugin, registered in `AdminPanelProvider::bootUsing`. The plugin's own resources are **hidden** and replaced by custom resources in `app/Filament/Resources/` (`PostResource`, `PageResource`, `NavigationResource`, `TagResource`). When extending CMS behavior, edit the local resources — not the vendor ones.

### Multi-authentication

- Admin uses Filament's built-in auth at `/admin`.
- Customers use a separate auth flow: `customer.guest` / `customer.auth` middleware, routes under `/login`, `/register`, `/customer/*`. Customer auth is implemented in `CustomerAuthController`.
- Since v1.3.0 / v1.3.1, both admin and customer identities are keyed on `user_id` (not `customer_id`); the same user can have both roles. Be careful when writing queries against rentals — they filter by `user_id`.

### Force HTTPS

`AppServiceProvider::boot()` calls `URL::forceScheme('https')` unconditionally. Local dev over plain HTTP will generate https URLs — this is intentional for deployment but can bite you locally.

## Planning documents in repo root

`administrasibaru.md`, `calendarfront.md`, `step.txt` are *planning documents written for the developer*, not current specs. They describe intended features (some already implemented, some partial). Treat them as context, not source of truth — verify against code.

## Things that regularly surprise

- **`composer update` / `composer install` triggers `filament:upgrade`** (post-autoload-dump). If Filament ships a breaking change this will run during any dep install.
- **Observers registered in `AppServiceProvider`** — easy to miss when tracing rental/finance side-effects.
- **Rental editor is NOT a Filament form** — Create/Edit rental render Livewire `App\Livewire\Admin\RentalEditor` via custom Blade view. `ItemsRelationManager` exists but is unrendered. New rental UI features go in the Livewire component + its Blade, not in Filament forms/relation managers.
- **Don't manually recalc rental totals after touching `RentalItem`** — `RentalItem::$touches = ['rental']` already triggers `RentalObserver::updated` which does full subtotal/discount/tax/total recalc (with `updateQuietly`). Manual `$rental->save()` after item changes will double-process and may overwrite the correct numbers. For the source rental in a cross-rental MOVE (where the item is no longer touching it), use `$rental->touch()` explicitly.
- **The `$isInstalled` gate wraps the entire routes file** in one big `if/else` — adding routes means putting them inside the `else` branch.
- **Duplicate stubs for debugging** live in the repo root (`check_classes_v2.php`, `check_finance_data.php`, `check_schema.php`, `debug_serial.php`, `test_decode.php`). These are throwaway scripts, not part of the app — don't import from them.
- **`layouts/guest.blade.php` is dual-mode**: it renders both `{{ $slot ?? '' }}` (component-style, e.g. `<x-guest-layout>` from `auth/*.blade.php`) AND `@yield('content')` (extends-style, used by `frontend/computers/mobile-kiosk-register*.blade.php`). Don't "clean it up" to one or the other without checking both consumer styles.
- **Carbon 3 `diffInSeconds` returns signed**: `$now->diffInSeconds($earlierDate)` returns NEGATIVE because the parameter is "from" not "to". Several places in the codebase historically used this incorrectly with unsigned-int columns (e.g. `actual_duration_seconds`). Fix is `max(0, (int) abs(...))`. Watch for this when reading old code.
- **`ProductUnitObserver` syncs kit serials**: when a `ProductUnit.serial_number` changes, `ProductUnitObserver::updated` silently (`updateQuietly`) updates all `UnitKit` rows that have `linked_unit_id = this unit` to carry the new serial. If you rename a serial via raw SQL and skip the observer, `KitUnitLinker` will fail to resolve that kit slot on the next save and may spawn a duplicate ghost unit.
- **`UnitKitObserver` guards self-reference**: `UnitKitObserver::saving` throws `DomainException` if a kit slot's serial matches its own parent unit's serial, or belongs to another unit of the same product. This guard fires on all save paths, including console/seeders.
- **Active rentals cannot be edited**: `Rental::canBeEdited()` returns false for `ACTIVE` / `LATE_RETURN` / `PARTIAL_RETURN` / `COMPLETED` / `CANCELLED`. `EditRental::mount()` redirects away from the editor if this returns false.
- **Kiosk register page polling** (`checkin-register.blade.php`): polls `/kiosk/checkin/{slug}/status` (JSON endpoint returning `{has_active_booking, booking_id}`) every 5s. Only redirects when an active checked-in booking exists — not on every poll. The earlier version unconditionally redirected on each tick which caused the kiosk to bounce back to home every 8s. `ComputerCheckinController::show()` also auto-redirects to `kiosk.timer` when an `ACTIVE` + `checked_in_at` booking exists, so the post-mobile-register transition is seamless.
