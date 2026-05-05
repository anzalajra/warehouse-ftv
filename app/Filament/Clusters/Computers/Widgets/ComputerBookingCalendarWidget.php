<?php

namespace App\Filament\Clusters\Computers\Widgets;

use App\Models\ComputerBooking;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ComputerBookingCalendarWidget extends FullCalendarWidget
{
    public function getRecord(): ?Model
    {
        return null;
    }

    protected function modalActions(): array
    {
        return [];
    }

    public function fetchEvents(array $info): array
    {
        $start = $info['start'] ?? null;
        $end = $info['end'] ?? null;

        $query = ComputerBooking::query()
            ->with(['user:id,name', 'computer:id,name']);

        if ($start && $end) {
            $query->whereBetween('booking_date', [
                substr($start, 0, 10),
                substr($end, 0, 10),
            ]);
        }

        return $query->limit(500)->get()->map(function (ComputerBooking $booking) {
            $color = match ($booking->status) {
                ComputerBooking::STATUS_CONFIRMED => '#3b82f6',
                ComputerBooking::STATUS_ACTIVE => '#22c55e',
                ComputerBooking::STATUS_COMPLETED => '#a855f7',
                ComputerBooking::STATUS_CANCELLED => '#6b7280',
                ComputerBooking::STATUS_NO_SHOW => '#ef4444',
                default => '#6b7280',
            };

            $startsAt = $booking->startsAt();
            $endsAt = $booking->endsAt();

            return EventData::make()
                ->id($booking->id)
                ->title(($booking->computer->name ?? '-').' — '.($booking->user->name ?? '-'))
                ->start($startsAt->toIso8601String())
                ->end($endsAt->toIso8601String())
                ->backgroundColor($color)
                ->borderColor($color)
                ->extendedProps([
                    'status' => $booking->status,
                    'code' => $booking->booking_code,
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
                'right' => 'dayGridMonth,listWeek,timeGridWeek',
            ],
            'initialView' => 'dayGridMonth',
            'eventMaxStack' => 3,
            'dayMaxEvents' => true,
            'moreLinkClick' => 'popover',
            'selectable' => false,
            'editable' => false,
            'eventDisplay' => 'block',
        ];
    }
}
