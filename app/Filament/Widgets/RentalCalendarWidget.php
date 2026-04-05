<?php

namespace App\Filament\Widgets;

use App\Models\Rental;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\Data\EventData;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

class RentalCalendarWidget extends FullCalendarWidget implements HasActions
{
    use InteractsWithActions;

    public function getRecord(): ?Model
    {
        return null;
    }

    protected function modalActions(): array
    {
        return [];
    }

    protected function viewAction(): Action
    {
        return Action::make('view')->hidden();
    }

    public function onEventClick(array $event): void
    {
        $this->mountAction('viewRentalDetails', ['rentalId' => $event['id']]);
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
                if (!isset($arguments['rentalId'])) return [];
                
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

    public function fetchEvents(array $info): array
    {
        $start = $info['start'] ?? null;
        $end = $info['end'] ?? null;

        $query = Rental::query()
            ->select(['id', 'user_id', 'rental_code', 'status', 'start_date', 'end_date', 'total', 'notes'])
            ->with([
                'customer:id,name',
                'items:id,rental_id,product_unit_id',
                'items.productUnit:id,serial_number,product_id',
                'items.productUnit.product:id,name',
            ]);

        if ($start && $end) {
            $query->where(function ($q) use ($start, $end) {
                $q->where('start_date', '<', $end)
                  ->where('end_date', '>', $start);
            });
        }

        $rentals = $query->limit(500)->get();

        return $rentals->map(function (Rental $rental) {
            $color = match ($rental->status) {
                'quotation' => '#f97316', // Orange
                'confirmed' => '#3b82f6', // Blue
                'active' => '#22c55e', // Green
                'completed' => '#a855f7', // Purple
                'cancelled' => '#6b7280', // Gray
                'late_pickup' => '#ef4444', // Red
                'late_return' => '#ef4444', // Red
                'partial_return' => '#eab308', // Yellow
                default => '#6b7280',
            };

            $items = $rental->items->map(function ($item) {
                $pu = $item->productUnit;
                return ($pu?->product?->name ?? '-') . ' (' . ($pu->serial_number ?? '-') . ')';
            })->join(', ');

            return EventData::make()
                ->id($rental->id)
                ->title($rental->customer->name . ' — ' . ($rental->rental_code ?? ''))
                ->start($rental->start_date?->toIso8601String())
                ->end($rental->end_date?->toIso8601String())
                ->backgroundColor($color)
                ->borderColor($color)
                ->extendedProps([
                    'status' => $rental->status,
                    'items' => $items,
                    'total' => 'Rp ' . number_format($rental->total ?? 0, 0, ',', '.'),
                ]);
        })->toArray();
    }

    public function config(): array
    {
        return [
            'firstDay' => 1,
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,listWeek',
            ],
            'initialView' => 'dayGridMonth',
            'eventMaxStack' => 3,
            'dayMaxEvents' => true,
            'moreLinkClick' => 'popover',
            'selectable' => false,
            'editable' => false,
            'selectMirror' => false,
            'eventDisplay' => 'block',
        ];
    }
}
