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

### The Rental domain is the center of gravity

Everything revolves around the `Rental` model and its lifecycle: `quotation` → `confirmed` → (active) → `returned` / `partial_return` / `cancelled`. Critical pieces:

- `app/Models/Rental.php` + `RentalItem` + `RentalItemKit` — rental with per-unit breakdown, including kit-level serial tracking.
- `app/Services/RentalValidationService.php` — double-booking prevention, product unit conflict resolution, date overlap checks. Any code that creates/edits rentals or checks availability should go through this service.
- `app/Observers/RentalObserver.php` — wired up in `AppServiceProvider::boot()`. Side-effects on rental state changes live here.
- `app/Http/Controllers/CheckoutController.php` (customer flow) and `app/Filament/Resources/Rentals/**` (admin flow) are the two entry points that create rentals; both must stay in sync on validation rules.
- `CatalogController::checkAvailability` powers live unit availability on the storefront — changes to availability logic must be mirrored in both the frontend calendar and this endpoint.

### Calendar / schedule views

There are **multiple** calendar surfaces — do not confuse them:

- `app/Filament/Pages/Schedule.php` and `GlobalProductSchedule.php` — admin schedule pages (by order / by product / timeline).
- `app/Filament/Pages/RentalCalendar.php` + `app/Filament/Widgets/RentalCalendarWidget.php` — saade/filament-fullcalendar based.
- `app/Http/Controllers/Frontend/ScheduleController.php` + `resources/views/frontend/schedule/index.blade.php` — public storefront calendar (`/schedule` and `/schedule/events`).
- Per-product availability calendar on the catalog detail page.

The `$colorMap` for rental statuses must stay consistent across these surfaces. `calendarfront.md` has design notes for the frontend calendar.

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

### Settings-driven runtime config

`App\Models\Setting` is a simple key/value store queried at boot in `AppServiceProvider`. It overrides:

- `app.name` (site_name)
- Mail config (mailer/host/port/from/etc. — only applied when `notification_email_enabled`)
- Document layout (`doc_*` keys auto-injected into all `pdf.*` views via a `View::composer`)
- Primary color theme (via `App\Services\ThemeService::getPrimaryColor()`) — used in **both** the Filament admin panel (`AdminPanelProvider`) and frontend layouts (`layouts.app`, `layouts.frontend`, `layouts.guest`). Keep these in sync; do not hardcode colors.

`AdminPanelProvider` also reads settings to choose `top` vs `sidebar` navigation and forces top-nav on detected mobile user agents. Test panel layout changes on both.

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
- **The `$isInstalled` gate wraps the entire routes file** in one big `if/else` — adding routes means putting them inside the `else` branch.
- **Duplicate stubs for debugging** live in the repo root (`check_classes_v2.php`, `check_finance_data.php`, `check_schema.php`, `debug_serial.php`, `test_decode.php`). These are throwaway scripts, not part of the app — don't import from them.
- **`layouts/guest.blade.php` is dual-mode**: it renders both `{{ $slot ?? '' }}` (component-style, e.g. `<x-guest-layout>` from `auth/*.blade.php`) AND `@yield('content')` (extends-style, used by `frontend/computers/mobile-kiosk-register*.blade.php`). Don't "clean it up" to one or the other without checking both consumer styles.
- **Carbon 3 `diffInSeconds` returns signed**: `$now->diffInSeconds($earlierDate)` returns NEGATIVE because the parameter is "from" not "to". Several places in the codebase historically used this incorrectly with unsigned-int columns (e.g. `actual_duration_seconds`). Fix is `max(0, (int) abs(...))`. Watch for this when reading old code.
- **Kiosk register page polling** (`checkin-register.blade.php`): polls `/kiosk/checkin/{slug}/status` (JSON endpoint returning `{has_active_booking, booking_id}`) every 5s. Only redirects when an active checked-in booking exists — not on every poll. The earlier version unconditionally redirected on each tick which caused the kiosk to bounce back to home every 8s. `ComputerCheckinController::show()` also auto-redirects to `kiosk.timer` when an `ACTIVE` + `checked_in_at` booking exists, so the post-mobile-register transition is seamless.
