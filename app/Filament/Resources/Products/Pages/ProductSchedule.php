<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Rental;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;

class ProductSchedule extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string $resource = ProductResource::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected string $view = 'filament.resources.products.pages.product-schedule';

    public Product $record;

    public Carbon $startDate;
    public Carbon $endDate;
    public array $days = [];

    public function mount(Product $record): void
    {
        $this->record = $record;
        $this->startDate = now()->startOfMonth();
        $this->endDate = now()->addMonths(2)->endOfMonth();
        $this->calculateDays();
    }

    protected function calculateDays(): void
    {
        $this->days = [];
        $current = $this->startDate->copy();
        while ($current <= $this->endDate) {
            $this->days[] = $current->copy();
            $current->addDay();
        }
    }

    public function nextMonth(): void
    {
        $this->startDate->addMonth();
        $this->endDate->addMonth();
        $this->calculateDays();
    }

    public function previousMonth(): void
    {
        $this->startDate->subMonth();
        $this->endDate->subMonth();
        $this->calculateDays();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewGlobalSchedule')
                ->label('View All Product Schedule')
                ->icon('heroicon-o-calendar')
                ->url(fn () => \App\Filament\Pages\Schedule::getUrl()),
        ];
    }

    public function getTitle(): string
    {
        return "Schedule: {$this->record->name}";
    }

    public function viewRentalDetailsAction(): Action
    {
        return Action::make('viewRentalDetails')
            ->modalHeading('Rental Details')
            ->modalWidth('2xl')
            ->form(fn (array $arguments) => [
                Grid::make(2)
                    ->schema([
                        TextInput::make('rental_code')
                            ->label('Rental Code')
                            ->disabled(),
                        TextInput::make('status')
                            ->label('Status')
                            ->disabled(),
                        TextInput::make('customer_name')
                            ->label('Customer')
                            ->disabled(),
                        TextInput::make('total')
                            ->label('Total Amount')
                            ->disabled(),
                        TextInput::make('start_date')
                            ->label('Start Date')
                            ->disabled(),
                        TextInput::make('end_date')
                            ->label('End Date')
                            ->disabled(),
                        Textarea::make('items')
                            ->label('Rented Units')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
            ])
            ->fillForm(function (array $arguments) {
                $rental = Rental::with(['customer', 'items.productUnit.product'])->find($arguments['rentalId']);
                if (!$rental) return [];

                $items = $rental->items->map(function ($item) {
                    $pu = $item->productUnit;
                    return ($pu?->product?->name ?? '-') . ' (' . ($pu->serial_number ?? '-') . ')';
                })->join("\n");

                return [
                    'rental_code' => $rental->rental_code,
                    'status' => ucfirst($rental->status),
                    'customer_name' => $rental->customer->name,
                    'total' => 'Rp ' . number_format($rental->total, 0, ',', '.'),
                    'start_date' => $rental->start_date->format('d M Y H:i'),
                    'end_date' => $rental->end_date->format('d M Y H:i'),
                    'items' => $items,
                    'notes' => $rental->notes,
                ];
            })
            ->modalFooterActions(fn (array $arguments) => [
                Action::make('viewRentalPage')
                    ->label('View Rental')
                    ->color('primary')
                    ->url(fn () => "/admin/rentals/{$arguments['rentalId']}/view"),
            ]);
    }

    public function getUnitsWithRentals(): array
    {
        $units = $this->record->units()->with(['rentalItems.rental.customer', 'rentalItems.deliveryItems.delivery'])->get();
        
        $data = [];
        foreach ($units as $unit) {
            $rentals = [];
            $overdue = [];
            foreach ($unit->rentalItems as $item) {
                $rental = $item->rental;
                if (! $rental) {
                    continue;
                }
                if (\App\Services\RentalOccupancyService::overdueHolder($item)) {
                    $overdue[$rental->id] = $rental->customer?->name ?? '—';
                }
                // Only include rentals that overlap with our view range
                if ($rental->end_date >= $this->startDate && $rental->start_date <= $this->endDate) {
                    $rentals[] = [
                        'id' => $rental->id,
                        'code' => $rental->rental_code,
                        'customer' => $rental->customer->name,
                        'start' => $rental->start_date,
                        'end' => $rental->end_date,
                        'status' => $rental->status,
                        'color' => \App\Models\Rental::getStatusColor($rental->status),
                    ];
                }
            }
            $data[] = [
                'unit' => $unit,
                'rentals' => $rentals,
                'overdue' => array_values($overdue),
            ];
        }
        
        return $data;
    }
}
