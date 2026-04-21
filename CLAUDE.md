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
