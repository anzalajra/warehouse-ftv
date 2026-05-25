<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerResource;
use App\Models\Computer;
use App\Models\ComputerRoom;
use App\Models\Setting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComputers extends ListRecords
{
    protected static string $resource = ComputerResource::class;

    protected string $view = 'filament.pages.computer-dashboard';

    public ?int $roomFilter = null;

    public ?string $statusFilter = null;

    public bool $onlineOnly = false;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Komputer Baru'),
        ];
    }

    public function getViewData(): array
    {
        $threshold = (int) (Setting::get('computer_kiosk_offline_threshold_seconds') ?? 60);

        $query = Computer::query()
            ->with('room:id,name')
            ->orderBy('room_id')
            ->orderBy('name');

        if ($this->roomFilter) {
            $query->where('room_id', $this->roomFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->onlineOnly) {
            $query->where('last_seen_at', '>=', now()->subSeconds($threshold));
        }

        $computers = $query->get();

        return [
            'computers' => $computers,
            'rooms' => ComputerRoom::orderBy('name')->get(['id', 'name']),
            'threshold' => $threshold,
            'stats' => [
                'total' => Computer::count(),
                'online' => Computer::where('last_seen_at', '>=', now()->subSeconds($threshold))->count(),
                'in_use' => $computers->filter(fn (Computer $c) => $c->currentBookingUser() !== null)->count(),
                'maintenance' => Computer::where('status', Computer::STATUS_MAINTENANCE)->count(),
            ],
            'roomFilter' => $this->roomFilter,
            'statusFilter' => $this->statusFilter,
            'onlineOnly' => $this->onlineOnly,
        ];
    }
}
