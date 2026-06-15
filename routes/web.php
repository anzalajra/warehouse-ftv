<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ComputerBookingController;
use App\Http\Controllers\ComputerController;
use App\Http\Controllers\CustomerDashboardController;
use App\Http\Controllers\Frontend\ScheduleController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// use App\Http\Controllers\Admin\PageBuilderController;
use App\Http\Controllers\PublicDocumentController;

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\File;

// Check installation status
$isInstalled = File::exists(storage_path('installed'));

if (!$isInstalled) {
    // If NOT installed, only allow setup routes and redirect root to setup
    Route::prefix('setup')->name('setup.')->group(function () {
        Route::get('/', [SetupController::class, 'index'])->name('index');
        Route::get('/step1', [SetupController::class, 'step1'])->name('step1');
        Route::post('/step2', [SetupController::class, 'step2'])->name('step2');
        Route::get('/step3', [SetupController::class, 'step3'])->name('step3');
        Route::get('/step4', [SetupController::class, 'step4'])->name('step4');
        Route::get('/step5', [SetupController::class, 'step5'])->name('step5');
        Route::post('/step6', [SetupController::class, 'step6'])->name('step6');
    });

    // Catch-all redirect to setup for root or any other route
    Route::get('/', function () {
        return redirect()->route('setup.index');
    });
    
    // Fallback to ensure everything goes to setup
    Route::fallback(function () {
        return redirect()->route('setup.index');
    });

} else {
    // If INSTALLED, load normal application routes
    // Setup routes are only available when not installed (handled in the if block above)

    // Public Routes
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // Schedule
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('frontend.schedule');
    Route::get('/schedule/day-rentals', [ScheduleController::class, 'dayRentals'])->name('frontend.schedule.day-rentals');
    Route::get('/schedule/rentals/{rental}', [ScheduleController::class, 'rentalDetails'])->name('frontend.schedule.rental-details');

    // Catalog
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{product}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::post('/catalog/check-availability/{unit}', [CatalogController::class, 'checkAvailability'])->name('catalog.check-availability');

    // Computer Booking (public list + detail + availability endpoint)
    Route::get('/computers', [ComputerController::class, 'index'])->name('computers.index');
    Route::get('/computers/rooms/{room}', [ComputerController::class, 'roomShow'])->name('computers.rooms.show');
    Route::get('/computers/{computer}', [ComputerController::class, 'show'])->name('computers.show');
    Route::post('/computers/{computer}/availability', [ComputerController::class, 'availability'])->name('computers.availability');

    // Computer Check-in Kiosk (public, slug-based)
    Route::get('/kiosk/checkin/{slug}', [App\Http\Controllers\ComputerCheckinController::class, 'show'])->name('kiosk.checkin');
    Route::post('/kiosk/checkin/{slug}', [App\Http\Controllers\ComputerCheckinController::class, 'checkin'])->name('kiosk.checkin.submit');
    Route::get('/kiosk/checkin/{slug}/other', [App\Http\Controllers\ComputerCheckinController::class, 'showOther'])->name('kiosk.checkin.other');
    Route::post('/kiosk/checkin/{slug}/other', [App\Http\Controllers\ComputerCheckinController::class, 'submitOther'])->name('kiosk.checkin.other.submit');
    Route::get('/kiosk/checkin/{slug}/register', [App\Http\Controllers\ComputerCheckinController::class, 'showRegister'])->name('kiosk.checkin.register');
    Route::get('/kiosk/checkin/{slug}/status', [App\Http\Controllers\ComputerCheckinController::class, 'status'])->name('kiosk.checkin.status');
    Route::get('/kiosk/checkin/{slug}/timer', [App\Http\Controllers\ComputerCheckinController::class, 'timer'])->name('kiosk.timer');
    Route::post('/kiosk/checkin/{slug}/logout', [App\Http\Controllers\ComputerCheckinController::class, 'logout'])->name('kiosk.logout');

    // Mobile register-and-checkin (when email tidak terdaftar, scan QR untuk daftar di HP)
    Route::get('/m/kiosk-register/{slug}', [App\Http\Controllers\KioskMobileRegisterController::class, 'show'])->name('mobile.kiosk-register');
    Route::post('/m/kiosk-register/{slug}', [App\Http\Controllers\KioskMobileRegisterController::class, 'register'])->middleware('throttle:6,1')->name('mobile.kiosk-register.submit');

    // Kiosk Desktop App API (Electron)
    Route::prefix('api/kiosk')->name('api.kiosk.')->group(function () {
        Route::post('/pair', [App\Http\Controllers\KioskApiController::class, 'pair'])->middleware('throttle:5,1')->name('pair');
        Route::post('/heartbeat', [App\Http\Controllers\KioskApiController::class, 'heartbeat'])->middleware('kiosk.auth')->name('heartbeat');
        Route::post('/heartbeat-web/{slug}', [App\Http\Controllers\KioskApiController::class, 'heartbeatWeb'])->middleware('throttle:6,1')->name('heartbeat.web');
        Route::post('/sync', [App\Http\Controllers\KioskApiController::class, 'sync'])->middleware('kiosk.auth')->name('sync');
        Route::get('/update/latest.yml', [App\Http\Controllers\KioskApiController::class, 'updateManifest'])->name('update.manifest');
        Route::get('/update/{file}', [App\Http\Controllers\KioskApiController::class, 'updateFile'])->where('file', '[A-Za-z0-9._-]+\.(exe|blockmap|yml)')->name('update.file');
    });

    // Customer Auth
    Route::middleware('customer.guest')->group(function () {
        Route::get('/login', [CustomerAuthController::class, 'showLoginForm'])->name('customer.login');
        Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:6,1');
        Route::get('/register', [CustomerAuthController::class, 'showRegistrationForm'])->name('customer.register');
        Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:6,1');
        
        // Password Reset
        Route::get('/forgot-password', [CustomerAuthController::class, 'showForgotPasswordForm'])->name('customer.password.request');
        Route::post('/forgot-password', [CustomerAuthController::class, 'sendResetLink'])->name('customer.password.email')->middleware('throttle:3,1');
        Route::get('/reset-password/{token}', [CustomerAuthController::class, 'showResetPasswordForm'])->name('customer.password.reset');
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])->name('customer.password.update')->middleware('throttle:3,1');
    });

    Route::match(['get', 'post'], '/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout')->middleware('customer.auth');

    // Customer Protected Routes
    Route::middleware('customer.auth')->prefix('customer')->name('customer.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [CustomerDashboardController::class, 'profile'])->name('profile');
        Route::put('/profile', [CustomerDashboardController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [CustomerDashboardController::class, 'updatePassword'])->name('password.update');
        Route::get('/rentals', [CustomerDashboardController::class, 'rentals'])->name('rentals');
        Route::get('/rentals/{id}', [CustomerDashboardController::class, 'rentalDetail'])->name('rental.detail');
        Route::post('/rentals/{rental}/mark-checklist-downloaded', [CustomerDashboardController::class, 'markChecklistDownloaded'])->name('rental.mark-checklist-downloaded');
        Route::post('/rentals/{rental}/mark-permit-clicked', [CustomerDashboardController::class, 'markPermitClicked'])->name('rental.mark-permit-clicked');

        Route::post('/blocked-popup/acknowledge', [CustomerDashboardController::class, 'acknowledgeBlockedPopup'])->name('blocked-popup.acknowledge');

        // Notifications
        Route::get('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

        // Computer Bookings (customer)
        Route::get('/computer-bookings', [ComputerBookingController::class, 'index'])->name('computer-bookings.index');
        Route::post('/computer-bookings', [ComputerBookingController::class, 'store'])->name('computer-bookings.store');
        Route::get('/computer-bookings/{booking}', [ComputerBookingController::class, 'show'])->name('computer-bookings.show');
        Route::post('/computer-bookings/{booking}/cancel', [ComputerBookingController::class, 'cancel'])->name('computer-bookings.cancel');
    });

    // Cart
    Route::middleware('customer.auth')->group(function () {
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
        Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
        Route::post('/cart/update-all', [CartController::class, 'updateAll'])->name('cart.update-all');
        Route::put('/cart/{cart}', [CartController::class, 'update'])->name('cart.update');
        Route::delete('/cart/product', [CartController::class, 'removeProduct'])->name('cart.remove-product');
        Route::patch('/cart/quantity', [CartController::class, 'updateQuantity'])->name('cart.update-quantity');
        Route::delete('/cart/{cart}', [CartController::class, 'remove'])->name('cart.remove');
        Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

        // Checkout
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'process'])->name('checkout.process');
        Route::post('/checkout/validate-discount', [CheckoutController::class, 'validateDiscount'])->name('checkout.validate-discount');
        Route::get('/checkout/success/{rental}', [CheckoutController::class, 'success'])->name('checkout.success');
    });

    // Customer Documents
    Route::middleware('customer.auth')->group(function () {
        Route::post('/customer/documents/upload', [App\Http\Controllers\CustomerDocumentController::class, 'upload'])->name('customer.documents.upload');
        Route::get('/customer/documents/{document}', [App\Http\Controllers\CustomerDocumentController::class, 'view'])->name('customer.documents.view');
        Route::delete('/customer/documents/{document}', [App\Http\Controllers\CustomerDocumentController::class, 'delete'])->name('customer.documents.delete');
    });

    // Admin PWA (manifest + service worker are public so browsers can fetch them
    // without an auth session; subscription endpoints require admin auth)
    Route::get('/admin/manifest.webmanifest', [App\Http\Controllers\AdminPwaController::class, 'manifest'])->name('admin.pwa.manifest');
    Route::get('/admin/manifest.json', [App\Http\Controllers\AdminPwaController::class, 'manifest'])->name('admin.pwa.manifest.json');
    Route::get('/admin/pwa-manifest', [App\Http\Controllers\AdminPwaController::class, 'manifest'])->name('admin.pwa.manifest.alt');
    Route::get('/admin/sw.js', [App\Http\Controllers\AdminPwaController::class, 'serviceWorker'])->name('admin.pwa.sw');
    Route::middleware(['auth'])->group(function () {
        Route::get('/admin/push/public-key', [App\Http\Controllers\AdminPwaController::class, 'publicKey'])->name('admin.pwa.public-key');
        Route::post('/admin/push/subscribe', [App\Http\Controllers\AdminPwaController::class, 'subscribe'])->name('admin.pwa.subscribe');
        Route::post('/admin/push/unsubscribe', [App\Http\Controllers\AdminPwaController::class, 'unsubscribe'])->name('admin.pwa.unsubscribe');
        Route::post('/admin/push/test', [App\Http\Controllers\AdminPwaController::class, 'test'])->name('admin.pwa.test');
    });

    // Redirect admin root to Home page
    Route::redirect('/admin', '/admin/home');

    // Admin Document View
    Route::middleware(['auth'])->group(function () {
        Route::get('/admin/documents/{document}/{filename?}', [App\Http\Controllers\CustomerDocumentController::class, 'viewForAdmin'])->name('admin.documents.view');
    });

    // Unit / kit label PNG (closed-system QR + Code128). Streamed as a download.
    Route::middleware(['auth'])->group(function () {
        Route::get('/admin/unit-label/{serial}/{type}', function (string $serial, string $type) {
            if (! in_array($type, ['qr', 'barcode'], true)) {
                abort(404);
            }

            $png = app(\App\Services\LabelImageService::class)->png($serial, $type);

            $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $serial);

            return response()->streamDownload(
                fn () => print ($png),
                "label-{$safe}-{$type}.png",
                ['Content-Type' => 'image/png']
            );
        })->where('serial', '[^/]+')->name('admin.unit-label');

        // Unit data feed for the Bluetooth label editor (Print Label page). The
        // editor iframe fetches this same-origin (session cookie) to import unit
        // serials without typing — each row carries the closed-system payload
        // (PREFIX:serial) used for both QR and barcode.
        Route::get('/admin/label-printer/units', function (\Illuminate\Http\Request $request) {
            $codes = app(\App\Services\UnitCodeService::class);

            $query = \App\Models\ProductUnit::query()->with(['product', 'kits']);

            if (filled($ids = $request->query('ids'))) {
                $idList = collect(explode(',', (string) $ids))
                    ->map(fn ($i) => (int) trim($i))
                    ->filter()
                    ->all();
                $query->whereIn('id', $idList);
            } elseif (strlen($q = trim((string) $request->query('q', ''))) >= 1) {
                $query->where(function ($w) use ($q) {
                    $w->where('serial_number', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"));
                })->limit(30);
            } else {
                $query->limit(20);
            }

            $rows = [];
            foreach ($query->get() as $unit) {
                if (filled($unit->serial_number)) {
                    $rows[] = [
                        'serial' => $unit->serial_number,
                        'name' => $unit->product->name ?? 'Unit',
                        'payload' => $codes->encode($unit->serial_number),
                        'type' => 'unit',
                    ];
                }
                foreach ($unit->kits as $kit) {
                    if (filled($kit->serial_number)) {
                        $rows[] = [
                            'serial' => $kit->serial_number,
                            'name' => $kit->name,
                            'payload' => $codes->encode($kit->serial_number),
                            'type' => 'kit',
                        ];
                    }
                }
            }

            return response()->json(['data' => $rows]);
        })->name('admin.label-printer.units');

        // Dedicated full-screen Bluetooth label editor (Print Label). Serves the
        // standalone LuckPrinter editor (public/vendor/luckprinter/editor.html) as
        // its own HTML document — NOT inside the Filament chrome, because the
        // editor ships its own global CSS/layout that would clash with the panel.
        // The system-data feed URL is injected so the editor's "import from system"
        // works; ?unit / ?units in the query are read client-side from location.
        Route::get('/admin/print-label', function (\Illuminate\Http\Request $request) {
            $path = public_path('vendor/luckprinter/editor.html');
            abort_unless(is_file($path), 404);

            $codes = app(\App\Services\UnitCodeService::class);

            // Resolve ?unit / ?units into an ordered print queue server-side (DB +
            // auth are available here) so the editor prefills + bulk-prints without
            // a client fetch round-trip. Each row carries the closed-system payload.
            $ids = [];
            if (filled($single = $request->query('unit'))) {
                $ids[] = (int) $single;
            }
            if (filled($many = $request->query('units'))) {
                foreach (explode(',', (string) $many) as $part) {
                    if (($id = (int) trim($part)) > 0) {
                        $ids[] = $id;
                    }
                }
            }
            $ids = array_values(array_unique(array_filter($ids)));

            $queue = [];
            if ($ids) {
                $units = \App\Models\ProductUnit::with(['product', 'kits'])
                    ->whereIn('id', $ids)->get()
                    ->sortBy(fn ($u) => array_search($u->id, $ids))->values();

                foreach ($units as $unit) {
                    if (filled($unit->serial_number)) {
                        $queue[] = [
                            'serial' => $unit->serial_number,
                            'name' => $unit->product->name ?? 'Unit',
                            'payload' => $codes->encode($unit->serial_number),
                            'type' => 'unit',
                        ];
                    }
                    foreach ($unit->kits as $kit) {
                        if (filled($kit->serial_number)) {
                            $queue[] = [
                                'serial' => $kit->serial_number,
                                'name' => $kit->name,
                                'payload' => $codes->encode($kit->serial_number),
                                'type' => 'kit',
                            ];
                        }
                    }
                }
            }

            // System logos (settings + brand logos) the editor can drop onto a label.
            $logos = [];
            $seen = [];
            $addLogo = function (string $name, $value) use (&$logos, &$seen) {
                if (blank($value)) {
                    return;
                }
                $url = \Illuminate\Support\Facades\Storage::url($value);
                if (isset($seen[$url])) {
                    return;
                }
                $seen[$url] = true;
                $logos[] = ['name' => $name, 'url' => $url];
            };
            $addLogo('Logo Situs', \App\Models\Setting::get('site_logo'));
            $addLogo('Logo Admin', \App\Models\Setting::get('logo'));
            $addLogo('Logo Dokumen', \App\Models\Setting::get('doc_logo'));
            $addLogo('Ikon Aplikasi', \App\Models\Setting::get('pwa_admin_icon'));
            foreach (\App\Models\Brand::whereNotNull('logo')->orderBy('name')->get() as $brand) {
                $addLogo('Brand: '.$brand->name, $brand->logo);
            }

            $calib = [
                'x' => (float) \App\Models\Setting::get('luckprinter_calib_x', 0),
                'y' => (float) \App\Models\Setting::get('luckprinter_calib_y', 0),
            ];

            // Server-saved label designs. The whole collection is injected so the
            // editor can list them without a fetch; the default design auto-fills
            // an incoming print queue (logo + bound name/serial/QR per unit).
            $templates = \App\Models\LabelTemplate::orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'is_default' => $t->is_default,
                    'design' => $t->design,
                ])->values();
            $defaultTemplate = optional($templates->firstWhere('is_default', true))['design'] ?? null;

            $html = (string) file_get_contents($path);
            $dataUrl = route('admin.label-printer.units', [], false);
            $calibUrl = route('admin.print-label.calib', [], false);
            $templatesUrl = route('admin.print-label.templates.index', [], false);
            $inject = '<script>window.LUCKPRINTER_DATA_URL='.json_encode($dataUrl)
                .';window.LUCKPRINTER_QUEUE='.json_encode($queue)
                .';window.LUCKPRINTER_LOGOS='.json_encode($logos)
                .';window.LUCKPRINTER_CALIB='.json_encode($calib)
                .';window.LUCKPRINTER_CALIB_URL='.json_encode($calibUrl)
                .';window.LUCKPRINTER_TEMPLATES='.json_encode($templates)
                .';window.LUCKPRINTER_TEMPLATES_URL='.json_encode($templatesUrl)
                .';window.LUCKPRINTER_DEFAULT_TEMPLATE='.json_encode($defaultTemplate)
                .';window.LUCKPRINTER_CSRF='.json_encode(csrf_token()).';</script>';
            $html = str_replace('</head>', $inject."\n</head>", $html);

            // Cache-bust the editor module bundle by the file mtimes so a redeploy /
            // file change is always picked up by the browser (no stale JS).
            $ver = 0;
            foreach (['editor-app.js', 'label.js', 'driver.js', 'devices.js'] as $f) {
                $fp = public_path('vendor/luckprinter/'.$f);
                if (is_file($fp)) {
                    $ver = max($ver, (int) filemtime($fp));
                }
            }
            $html = str_replace(
                '/vendor/luckprinter/editor-app.js',
                '/vendor/luckprinter/editor-app.js?v='.$ver,
                $html
            );

            return response($html)
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        })->name('admin.print-label');

        // Save Bluetooth label editor print-position calibration to the database.
        Route::post('/admin/print-label/calibration', function (\Illuminate\Http\Request $request) {
            $x = round((float) $request->input('x', 0), 2);
            $y = round((float) $request->input('y', 0), 2);
            \App\Models\Setting::set('luckprinter_calib_x', (string) $x);
            \App\Models\Setting::set('luckprinter_calib_y', (string) $y);
            return response()->json(['ok' => true, 'x' => $x, 'y' => $y]);
        })->name('admin.print-label.calib');

        // ---- Server-saved label templates (CRUD for the Bluetooth editor) ----
        // Mirrors the calib endpoint: auth-gated, CSRF via window.LUCKPRINTER_CSRF.
        Route::get('/admin/print-label/templates', function () {
            $rows = \App\Models\LabelTemplate::orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'design', 'is_default']);

            return response()->json(['data' => $rows]);
        })->name('admin.print-label.templates.index');

        Route::post('/admin/print-label/templates', function (\Illuminate\Http\Request $request) {
            $data = $request->validate([
                'id' => ['nullable', 'integer', 'exists:label_templates,id'],
                'name' => ['required', 'string', 'max:120'],
                'design' => ['required', 'array'],
                'is_default' => ['sometimes', 'boolean'],
            ]);

            $template = filled($data['id'] ?? null)
                ? \App\Models\LabelTemplate::findOrFail($data['id'])
                : new \App\Models\LabelTemplate;

            $template->fill([
                'name' => $data['name'],
                'design' => $data['design'],
            ]);
            // Avoid clobbering an existing default flag when not provided.
            if ($request->boolean('is_default')) {
                $template->is_default = true;
            }
            $template->save();

            if ($request->boolean('is_default')) {
                $template->setAsDefault();
            }

            return response()->json([
                'ok' => true,
                'template' => $template->only(['id', 'name', 'design', 'is_default']),
            ]);
        })->name('admin.print-label.templates.store');

        Route::delete('/admin/print-label/templates/{template}', function (\App\Models\LabelTemplate $template) {
            $template->delete();

            return response()->json(['ok' => true]);
        })->name('admin.print-label.templates.destroy');

        // Resolve a scanned unit/kit code (PREFIX:serial) from the global admin QR
        // scanner into the product's catalog edit page — same decode contract as the
        // Pickup/Return scanner (UnitCodeService). Accepts a bare serial as fallback.
        Route::get('/admin/scan-resolve', function (\Illuminate\Http\Request $request) {
            $codes = app(\App\Services\UnitCodeService::class);
            $raw = trim((string) $request->query('code', ''));
            if ($raw === '') {
                return response()->json(['ok' => false, 'message' => 'Kode kosong.'], 422);
            }

            // Closed-system code first; fall back to treating the scan as a raw serial.
            $serial = $codes->decode($raw) ?? $raw;

            $unit = \App\Models\ProductUnit::where('serial_number', $serial)->first();
            if (! $unit) {
                $kit = \App\Models\UnitKit::where('serial_number', $serial)->first();
                $unit = $kit ? \App\Models\ProductUnit::find($kit->unit_id) : null;
            }

            $product = $unit?->product;
            if (! $product) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Serial tidak ditemukan: '.$serial,
                ], 404);
            }

            // Build the admin catalog edit URL directly — Filament's getUrl() needs the
            // panel context which isn't bootstrapped inside this plain web route.
            return response()->json([
                'ok' => true,
                'url' => url('/admin/products/'.$product->getKey().'/edit'),
                'label' => trim(($product->name ?? 'Produk').' · '.$serial),
            ]);
        })->name('admin.scan-resolve');
    });

    // User Impersonation (admin -> customer)
    Route::middleware(['auth'])->group(function () {
        Route::get('/admin/impersonate/{user}', [App\Http\Controllers\ImpersonateController::class, 'start'])->name('impersonate.start');
    });
    Route::get('/impersonate/stop', [App\Http\Controllers\ImpersonateController::class, 'stop'])->name('impersonate.stop');

    // Backup Download — restrict to whitelisted filename pattern + canonical-path check
    // to prevent path traversal if the stored filename is ever malformed.
    Route::middleware(['auth'])->group(function () {
        Route::get('/admin/backup/download/{backupHistory}', function (\App\Models\BackupHistory $backupHistory) {
            $filename = (string) $backupHistory->filename;

            // Whitelist: only safe chars allowed (alnum, dash, underscore, dot). No path separators.
            if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
                abort(404, 'Invalid backup filename.');
            }

            $baseDir = realpath(storage_path('app/backups'));
            $path    = realpath(storage_path('app/backups/' . $filename));

            if ($baseDir === false || $path === false || strpos($path, $baseDir . DIRECTORY_SEPARATOR) !== 0) {
                abort(404, 'Backup file not found.');
            }

            return response()->download($path);
        })->name('backup.download');
    });

    // Public Signed Documents
    Route::prefix('public-documents')->name('public-documents.')->group(function () {
        Route::get('/rental/{rental}/checklist', [PublicDocumentController::class, 'rentalChecklist'])->name('rental.checklist');
        Route::get('/rental/{rental}/delivery-note', [PublicDocumentController::class, 'rentalDeliveryNote'])->name('rental.delivery-note');
        Route::get('/delivery-note/{delivery}', [PublicDocumentController::class, 'deliveryNote'])->name('delivery-note');
        Route::get('/quotation/{quotation}', [PublicDocumentController::class, 'quotation'])->name('quotation');
        Route::get('/invoice/{invoice}', [PublicDocumentController::class, 'invoice'])->name('invoice');
    });

    // Lara Zeus Sky Routes
    Route::prefix('blog')->middleware(['web'])->group(function () {
        Route::get('/', \LaraZeus\Sky\Livewire\Posts::class)->name('blogs');
        Route::get('/faq', \LaraZeus\Sky\Livewire\Faq::class)->name('faq');
        
        Route::get('/tag/{slug}', \LaraZeus\Sky\Livewire\Tags::class)
            ->defaults('type', 'tag')
            ->name('tag');
            
        Route::get('/category/{slug}', \LaraZeus\Sky\Livewire\Tags::class)
            ->defaults('type', 'category')
            ->name('category');

        Route::get('/{slug}', \LaraZeus\Sky\Livewire\Post::class)->name('post');
    });

    // Embeddable page content (for iframe modals — no layout chrome)
    Route::get('/page-embed/{slug}', function (string $slug) {
        $page = \App\Models\Zeus\Post::query()
            ->where('post_type', 'page')
            ->where('slug', $slug)
            ->firstOrFail();
        return response()
            ->view('frontend.page-embed', ['page' => $page])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    })->name('page.embed');

    // Lara Zeus Sky Pages (Direct Access)
    Route::middleware(['web'])->group(function () {
        Route::get('/{slug}', \LaraZeus\Sky\Livewire\Page::class)->name('page');
    });
}
