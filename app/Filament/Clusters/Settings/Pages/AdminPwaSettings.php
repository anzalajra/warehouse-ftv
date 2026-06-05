<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use App\Services\WebPushService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AdminPwaSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Admin App & Push';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.clusters.settings.pages.admin-pwa-settings';

    public ?array $data = [];

    /**
     * Notification classes that can be pushed. key => human label.
     */
    public static function notificationTypes(): array
    {
        return [
            \App\Notifications\NewBookingNotification::class => 'Booking baru / Rental baru',
            \App\Notifications\NewCustomerNotification::class => 'Customer baru mendaftar',
            \App\Notifications\VerificationRequestNotification::class => 'Permintaan verifikasi customer',
            \App\Notifications\DocumentVerifiedNotification::class => 'Dokumen customer diverifikasi',
            \App\Notifications\InvoiceCreatedNotification::class => 'Invoice baru dibuat',
            \App\Notifications\DeliveryOutNotification::class => 'Surat jalan keluar',
            \App\Notifications\DeliveryInNotification::class => 'Surat jalan masuk (return)',
            \App\Notifications\RentalCompletedNotification::class => 'Rental selesai',
            \App\Notifications\PickupReminderNotification::class => 'Reminder pickup',
            \App\Notifications\ReturnReminderNotification::class => 'Reminder return',
            \App\Notifications\DailyReminderSummaryNotification::class => 'Reminder harian (gabungan H-1 pickup & return)',
            \App\Notifications\OverdueAlertNotification::class => 'Rental telat / overdue',
            \App\Notifications\BookingConfirmedNotification::class => 'Booking dikonfirmasi',
            \App\Notifications\MaintenanceReminderNotification::class => 'Reminder maintenance',
            \App\Notifications\SystemErrorNotification::class => 'System error',
        ];
    }

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Block-list is stored per class as boolean setting. The form uses
        // *positive* toggles ("push this notification?"), so invert here.
        foreach (self::notificationTypes() as $class => $label) {
            $key = $this->toggleKey($class);
            $blockedKey = $this->blockedKey($class);
            $settings[$key] = ! Setting::get($blockedKey, false);
        }

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        $push = app(WebPushService::class);

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Web Push Status')
                    ->description('Notifikasi push membutuhkan VAPID keys di .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT). Generate dengan `php artisan push:generate-vapid`.')
                    ->schema([
                        Placeholder::make('vapid_status')
                            ->label('VAPID Configured')
                            ->content(new HtmlString(
                                $push->isConfigured()
                                    ? '<span style="color:#16a34a;font-weight:600;">&#10003; Configured</span>'
                                    : '<span style="color:#dc2626;font-weight:600;">&#10007; Not configured — jalankan <code>php artisan push:generate-vapid</code> atau set env manual.</span>'
                            )),
                        Toggle::make('pwa_admin_push_enabled')
                            ->label('Enable Push Notifications')
                            ->helperText('Master switch. Saat OFF, tidak ada push notification yang dikirim ke device admin.')
                            ->default(true),
                    ])->columns(1),

                Section::make('App Identity')
                    ->description('Identitas aplikasi terinstall (PWA) di home screen device admin.')
                    ->schema([
                        TextInput::make('pwa_admin_name')
                            ->label('App Name')
                            ->placeholder('Warehouse FTV')
                            ->default('Warehouse FTV')
                            ->required(),
                        TextInput::make('pwa_admin_short_name')
                            ->label('Short Name')
                            ->placeholder('Warehouse FTV')
                            ->helperText('Nama pendek yang muncul di bawah icon home screen (maks 12 karakter).')
                            ->maxLength(12),
                        TextInput::make('pwa_admin_background_color')
                            ->label('Splash Background Color')
                            ->placeholder('#ffffff')
                            ->default('#ffffff')
                            ->helperText('Warna splash screen saat aplikasi dibuka.'),
                    ])->columns(3),

                Section::make('Reminder Harian')
                    ->description('Reminder gabungan H-1 pickup & return dikirim otomatis setiap hari ke semua admin. Atur jam pengirimannya di bawah. Reminder tetap dikirim walau tidak ada rental besok, sehingga tidak adanya notifikasi pasti berarti scheduler bermasalah — bukan sekadar tidak ada data.')
                    ->schema([
                        TimePicker::make('daily_reminder_time')
                            ->label('Jam Kirim Reminder Harian')
                            ->seconds(false)
                            ->format('H:i')
                            ->displayFormat('H:i')
                            ->default('17:00')
                            ->helperText('Membutuhkan scheduler aktif (cron menjalankan `php artisan schedule:run` tiap menit).')
                            ->required(),
                        Placeholder::make('daily_reminder_test_hint')
                            ->label('')
                            ->content(new HtmlString(
                                'Gunakan tombol <strong>Kirim Test Reminder Harian</strong> di pojok kanan atas untuk mengirim reminder hari ini ke akun Anda sekarang juga.'
                            )),
                    ])->columns(1),

                Section::make('Notification Types')
                    ->description('Pilih notifikasi mana yang dikirim sebagai push ke device admin. Notifikasi yang dimatikan tetap muncul di lonceng admin panel, tapi tidak push ke HP.')
                    ->schema(
                        collect(self::notificationTypes())
                            ->map(fn ($label, $class) => Toggle::make($this->toggleKey($class))
                                ->label($label)
                                ->default(true)
                            )
                            ->values()
                            ->all()
                    )
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'pwa_admin_push_class_')) {
                continue; // handled below
            }
            Setting::set($key, $value);
        }

        // Persist class block-list (inverse of UI toggle).
        foreach (self::notificationTypes() as $class => $label) {
            $key = $this->toggleKey($class);
            $blockedKey = $this->blockedKey($class);
            $enabled = (bool) ($data[$key] ?? true);
            Setting::set($blockedKey, ! $enabled);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function sendTestPush(): void
    {
        $push = app(WebPushService::class);
        if (! $push->isConfigured()) {
            Notification::make()
                ->title('VAPID belum dikonfigurasi')
                ->body('Jalankan php artisan push:generate-vapid atau set env manual.')
                ->danger()
                ->send();
            return;
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) return;

        $count = \App\Models\PushSubscription::where('user_id', $user->id)->count();
        if ($count === 0) {
            Notification::make()
                ->title('Belum ada device terdaftar untuk user ini')
                ->body('Buka admin panel dari HP, install sebagai aplikasi, lalu izinkan notifikasi.')
                ->warning()
                ->send();
            return;
        }

        $push->sendToUser($user->id, [
            'title' => Setting::get('pwa_admin_name', 'Warehouse FTV'),
            'body' => 'Ini test notification. Push berhasil!',
            'url' => '/admin',
            'tag' => 'test-' . time(),
        ]);

        Notification::make()
            ->title('Test push dikirim ke ' . $count . ' device')
            ->success()
            ->send();
    }

    public function sendTestDailyReminder(): void
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user) {
            return;
        }

        $tomorrow = now()->addDay()->toDateString();

        $pickupCount = \App\Models\Rental::whereIn('status', [
            \App\Models\Rental::STATUS_QUOTATION,
            \App\Models\Rental::STATUS_CONFIRMED,
        ])->whereDate('start_date', $tomorrow)->count();

        $returnCount = \App\Models\Rental::whereIn('status', [
            \App\Models\Rental::STATUS_ACTIVE,
            \App\Models\Rental::STATUS_LATE_PICKUP,
            \App\Models\Rental::STATUS_LATE_RETURN,
        ])->whereDate('end_date', '<=', $tomorrow)->count();

        // Goes through the database channel, which the web-push listener mirrors
        // to the admin's device(s) — same path as the real scheduled reminder.
        $user->notify(new \App\Notifications\DailyReminderSummaryNotification(
            $pickupCount,
            $returnCount,
            $tomorrow,
        ));

        Notification::make()
            ->title('Test reminder harian dikirim')
            ->body("Untuk besok: {$pickupCount} pickup, {$returnCount} return. Cek lonceng admin & notifikasi di HP.")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestPush')
                ->label('Kirim Test Push')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->action('sendTestPush'),
            Action::make('sendTestDailyReminder')
                ->label('Kirim Test Reminder Harian')
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->action('sendTestDailyReminder'),
        ];
    }

    protected function toggleKey(string $class): string
    {
        return 'pwa_admin_push_class_' . strtolower(str_replace('\\', '_', $class));
    }

    protected function blockedKey(string $class): string
    {
        return 'pwa_admin_push_block_' . strtolower(str_replace('\\', '_', $class));
    }
}
