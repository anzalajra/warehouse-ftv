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
