<?php

namespace App\Filament\Resources\Maintenances\Pages;

use App\Filament\Resources\Maintenances\MaintenanceResource;
use App\Filament\Resources\Maintenances\Widgets\MaintenanceStatsOverview;
use App\Models\MaintenanceRecord;
use App\Models\ProductUnit;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageMaintenances extends ManageRecords
{
    protected static string $resource = MaintenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendToMaintenance')
                ->label('Kirim Unit ke Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->modalHeading('Kirim Unit ke Maintenance')
                ->modalSubmitActionLabel('Kirim')
                ->form([
                    Select::make('product_unit_id')
                        ->label('Unit')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => ProductUnit::query()
                            ->where('status', '!=', ProductUnit::STATUS_RETIRED)
                            ->where(fn (Builder $q) => $q
                                ->where('serial_number', 'like', "%{$search}%")
                                ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$search}%")))
                            ->with('product')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (ProductUnit $u) => [
                                $u->id => ($u->product->name ?? 'Unit')." — {$u->serial_number}",
                            ]))
                        ->getOptionLabelUsing(function ($value) {
                            $u = ProductUnit::with('product')->find($value);

                            return $u ? (($u->product->name ?? 'Unit')." — {$u->serial_number}") : null;
                        }),
                    Select::make('type')
                        ->label('Jenis')
                        ->options(MaintenanceRecord::getTypeOptions())
                        ->default(MaintenanceRecord::TYPE_PREVENTIVE)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan / Catatan')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $unit = ProductUnit::findOrFail($data['product_unit_id']);
                    $unit->sendToMaintenance($data['reason'], $data['type']);

                    Notification::make()
                        ->title('Unit dikirim ke Maintenance')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenanceStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        $qcInterval = (int) Setting::get('maintenance_qc_interval_days', 90);

        return [
            'active' => Tab::make('Aktif')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q
                    ->whereIn('condition', ['broken', 'lost'])
                    ->orWhere('status', ProductUnit::STATUS_MAINTENANCE)
                    ->orWhereHas('kits', fn (Builder $k) => $k->whereIn('condition', ['broken', 'lost'])))),

            'qc_due' => Tab::make('QC Jatuh Tempo')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', ProductUnit::STATUS_AVAILABLE)
                    ->where(fn (Builder $q) => $q
                        ->whereNull('last_checked_at')
                        ->orWhere('last_checked_at', '<', now()->subDays(max(1, $qcInterval))))),

            'all' => Tab::make('Semua'),
        ];
    }
}
