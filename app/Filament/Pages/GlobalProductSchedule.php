<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Rental;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Livewire\WithPagination;
use UnitEnum;

class GlobalProductSchedule extends Page implements HasActions
{
    use InteractsWithActions;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $title = 'Global Schedule';

    protected static ?string $navigationLabel = 'Global Schedule';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.global-product-schedule';

    public Carbon $startDate;

    public Carbon $endDate;

    public array $days = [];

    public function mount(): void
    {
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
                    ]),
            ])
            ->fillForm(function (array $arguments) {
                $rental = Rental::with(['customer', 'items.productUnit.product'])->find($arguments['rentalId']);
                if (! $rental) {
                    return [];
                }

                $items = $rental->items->map(function ($item) {
                    $pu = $item->productUnit;

                    return ($pu?->product?->name ?? '-').' ('.($pu->serial_number ?? '-').')';
                })->join("\n");

                return [
                    'rental_code' => $rental->rental_code,
                    'status' => ucfirst($rental->status),
                    'customer_name' => $rental->customer->name,
                    'total' => 'Rp '.number_format($rental->total, 0, ',', '.'),
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

    public function getProductsWithUnitsAndRentals(): Paginator
    {
        $products = Product::with(['units.rentalItems.rental.customer', 'units.rentalItems.deliveryItems.delivery'])
            ->whereHas('units')
            ->paginate(5); // Adjust items per page as needed

        $products->getCollection()->transform(function ($product) {
            $productData = [
                'product' => $product,
                'units' => [],
            ];

            foreach ($product->units as $unit) {
                $rentals = [];
                foreach ($unit->rentalItems as $item) {
                    $rental = $item->rental;
                    foreach (\App\Services\RentalOccupancyService::scheduleBlocks($item, $this->endDate) as $block) {
                        if ($block['end'] >= $this->startDate && $block['start'] <= $this->endDate) {
                            $rentals[] = [
                                'id' => $rental->id,
                                'code' => $rental->rental_code,
                                'customer' => $rental->customer->name,
                                'start' => $block['start'],
                                'end' => $block['end'],
                                'status' => $block['status'],
                                'color' => $block['status'] === 'over_time' ? 'purple' : \App\Models\Rental::getStatusColor($rental->status),
                            ];
                        }
                    }
                }
                $productData['units'][] = [
                    'unit' => $unit,
                    'rentals' => $rentals,
                ];
            }

            return $productData;
        });

        return $products;
    }
}
