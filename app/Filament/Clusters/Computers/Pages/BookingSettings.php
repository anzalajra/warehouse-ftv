<?php

namespace App\Filament\Clusters\Computers\Pages;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Models\ComputerBookingSlot;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class BookingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = ComputersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Computer Booking Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.computer-booking-settings-cluster';

    public ?array $data = [];

    public const DAYS = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        0 => 'Minggu',
    ];

    public function mount(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $form = [
            'computer_quota_hours_per_week' => Setting::get('computer_quota_hours_per_week') ?? 6,
            'computer_quota_slots_per_day' => Setting::get('computer_quota_slots_per_day') ?? 1,
            'computer_no_show_grace_minutes' => Setting::get('computer_no_show_grace_minutes') ?? 30,
            'computer_night_permit_text' => Setting::get('computer_night_permit_text') ?? 'Saya menyatakan telah memiliki izin tertulis untuk menggunakan lab di luar jam operasional dan menanggung segala risiko terkait.',
            'computer_night_permit_required' => (bool) (Setting::get('computer_night_permit_required') ?? true),
            'computer_tnc_text' => Setting::get('computer_tnc_text') ?? "Dilarang makan & minum di meja komputer.\nWajib backup data mandiri.\nLaporkan kerusakan kepada admin lab.",
            'computer_kiosk_offline_threshold_seconds' => Setting::get('computer_kiosk_offline_threshold_seconds') ?? 60,
            'computer_kiosk_heartbeat_interval_seconds' => Setting::get('computer_kiosk_heartbeat_interval_seconds') ?? 30,
            'computer_kiosk_running_apps_whitelist' => Setting::get('computer_kiosk_running_apps_whitelist') ?? "Adobe Premiere Pro.exe\nAfterFX.exe\nPhotoshop.exe\nIllustrator.exe\nResolve.exe\nOBS64.exe\nobs64.exe\nAudacity.exe\nAudition.exe",
            'computer_kiosk_latest_version' => Setting::get('computer_kiosk_latest_version') ?? '',
            'computer_kiosk_admin_pin' => Setting::get('computer_kiosk_admin_pin') ?? '9999',
        ];

        $byDay = ComputerBookingSlot::orderBy('start_time')->get()->groupBy('day_of_week');
        foreach (self::DAYS as $dow => $label) {
            $rows = $byDay->get($dow, collect());
            $form['day_'.$dow.'_enabled'] = $rows->where('is_active', true)->count() > 0;
            $form['day_'.$dow.'_slots'] = $rows->map(fn ($r) => [
                'start_time' => $r->start_time,
                'end_time' => $r->end_time,
                'is_night' => (bool) $r->is_night,
            ])->values()->toArray();
        }

        $this->form->fill($form);
    }

    public function form(Schema $schema): Schema
    {
        $daySections = [];
        foreach (self::DAYS as $dow => $label) {
            $daySections[] = Section::make($label)
                ->schema([
                    Toggle::make('day_'.$dow.'_enabled')
                        ->label('Aktifkan '.$label)
                        ->live(),
                    Repeater::make('day_'.$dow.'_slots')
                        ->label('Slot Jam')
                        ->visible(fn (callable $get) => (bool) $get('day_'.$dow.'_enabled'))
                        ->schema([
                            Grid::make(3)->schema([
                                TimePicker::make('start_time')->seconds(false)->required()->label('Mulai'),
                                TimePicker::make('end_time')->seconds(false)->required()->label('Selesai'),
                                Toggle::make('is_night')->label('Jam Malam')->inline(false),
                            ]),
                        ])
                        ->addActionLabel('Tambah Jam')
                        ->reorderable(false)
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(fn (callable $get) => ! (bool) $get('day_'.$dow.'_enabled'));
        }

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')
                    ->tabs([
                        Tab::make('Jam Operasional')
                            ->icon('heroicon-o-clock')
                            ->schema($daySections),

                        Tab::make('Permit Jam Malam')
                            ->icon('heroicon-o-moon')
                            ->schema([
                                Section::make()
                                    ->description('Slot dengan toggle "Jam Malam" akan menampilkan banner permit ini. Customer wajib centang sebelum booking.')
                                    ->schema([
                                        Toggle::make('computer_night_permit_required')
                                            ->label('Wajibkan permit untuk slot malam')
                                            ->helperText('Jika dimatikan, customer tetap bisa booking slot malam tanpa centang permit.'),
                                        Textarea::make('computer_night_permit_text')
                                            ->label('Teks Permit Jam Malam')
                                            ->rows(5)
                                            ->required()
                                            ->helperText('Ditampilkan saat user booking slot dengan flag is_night.'),
                                    ]),
                            ]),

                        Tab::make('Kuota Customer')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('computer_quota_hours_per_week')
                                            ->label('Maks Jam per Minggu')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->helperText('Set 0 untuk menonaktifkan kuota mingguan.'),
                                        TextInput::make('computer_quota_slots_per_day')
                                            ->label('Maks Slot per Hari')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->helperText('Set 0 untuk menonaktifkan kuota harian.'),
                                        TextInput::make('computer_no_show_grace_minutes')
                                            ->label('No-Show Grace (menit)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->required()
                                            ->helperText('Booking auto no-show jika tidak check-in dalam X menit setelah jam mulai.'),
                                    ])->columns(3),
                            ]),

                        Tab::make('Kiosk Desktop App')
                            ->icon('heroicon-o-computer-desktop')
                            ->schema([
                                Section::make()
                                    ->description('Pengaturan integrasi aplikasi kiosk desktop di komputer lab.')
                                    ->schema([
                                        TextInput::make('computer_kiosk_offline_threshold_seconds')
                                            ->label('Offline Threshold (detik)')
                                            ->numeric()->minValue(15)->required()
                                            ->helperText('Komputer dianggap offline kalau heartbeat lebih lama dari nilai ini.'),
                                        TextInput::make('computer_kiosk_heartbeat_interval_seconds')
                                            ->label('Heartbeat Interval (detik)')
                                            ->numeric()->minValue(10)->required(),
                                        TextInput::make('computer_kiosk_latest_version')
                                            ->label('Latest App Version')
                                            ->placeholder('1.0.1')
                                            ->helperText('Diisi setelah upload release baru ke storage/app/kiosk-releases/.'),
                                        TextInput::make('computer_kiosk_admin_pin')
                                            ->label('Admin PIN (Kiosk Close)')
                                            ->password()->revealable()->required()
                                            ->helperText('PIN untuk Ctrl+Shift+W di kiosk app.'),
                                        Textarea::make('computer_kiosk_running_apps_whitelist')
                                            ->label('Whitelist Aplikasi (1 .exe per baris)')
                                            ->rows(8)->required()->columnSpanFull(),
                                    ])->columns(2),
                            ]),

                        Tab::make('Syarat & Ketentuan')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('computer_tnc_text')
                                            ->label('Teks Syarat & Ketentuan')
                                            ->rows(8)
                                            ->required()
                                            ->helperText('Ditampilkan di form booking customer. Wajib di-accept sebelum submit.'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Semua')
                ->color('primary')
                ->icon('heroicon-o-check')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        DB::transaction(function () use ($state) {
            // Slots
            ComputerBookingSlot::query()->delete();
            foreach (self::DAYS as $dow => $label) {
                $enabled = (bool) ($state['day_'.$dow.'_enabled'] ?? false);
                $slots = $state['day_'.$dow.'_slots'] ?? [];
                if (! $enabled || empty($slots)) {
                    continue;
                }
                foreach ($slots as $slot) {
                    if (empty($slot['start_time']) || empty($slot['end_time'])) {
                        continue;
                    }
                    ComputerBookingSlot::create([
                        'day_of_week' => $dow,
                        'start_time' => substr($slot['start_time'], 0, 5),
                        'end_time' => substr($slot['end_time'], 0, 5),
                        'is_active' => true,
                        'is_night' => (bool) ($slot['is_night'] ?? false),
                    ]);
                }
            }

            // Settings
            foreach ([
                'computer_quota_hours_per_week',
                'computer_quota_slots_per_day',
                'computer_no_show_grace_minutes',
                'computer_night_permit_text',
                'computer_night_permit_required',
                'computer_tnc_text',
                'computer_kiosk_offline_threshold_seconds',
                'computer_kiosk_heartbeat_interval_seconds',
                'computer_kiosk_running_apps_whitelist',
                'computer_kiosk_latest_version',
                'computer_kiosk_admin_pin',
            ] as $key) {
                if (array_key_exists($key, $state)) {
                    Setting::set($key, $state[$key]);
                }
            }
        });

        Notification::make()
            ->title('Pengaturan tersimpan')
            ->success()
            ->send();

        $this->loadData();
    }
}
