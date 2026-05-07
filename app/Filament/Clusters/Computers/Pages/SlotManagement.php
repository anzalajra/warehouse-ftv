<?php

namespace App\Filament\Clusters\Computers\Pages;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Models\ComputerBookingSlot;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class SlotManagement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = ComputersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Slot Management';

    protected static ?string $title = 'Slot Management';

    protected static ?string $slug = 'slots';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.slot-management';

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
        $byDay = ComputerBookingSlot::orderBy('start_time')->get()->groupBy('day_of_week');

        $form = [];
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
        $sections = [];
        foreach (self::DAYS as $dow => $label) {
            $sections[] = Section::make($label)
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
                ->collapsible();
        }

        return $schema->components($sections)->statePath('data');
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
        });

        Notification::make()
            ->title('Slot tersimpan')
            ->success()
            ->send();

        $this->loadData();
    }
}
