<?php

namespace App\Filament\Clusters\Computers\Pages;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Widgets\ComputerBookingCalendarWidget;
use BackedEnum;
use Filament\Pages\Page;

class ComputerBookingCalendar extends Page
{
    protected static ?string $cluster = ComputersCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Booking Calendar';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.computer-booking-calendar';

    protected function getHeaderWidgets(): array
    {
        return [
            ComputerBookingCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
