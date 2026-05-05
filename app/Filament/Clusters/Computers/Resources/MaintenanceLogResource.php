<?php

namespace App\Filament\Clusters\Computers\Resources;

use App\Filament\Clusters\Computers\ComputersCluster;
use App\Filament\Clusters\Computers\Resources\MaintenanceLogResource\Pages\ListMaintenanceLogs;
use App\Filament\Clusters\Computers\Resources\MaintenanceLogResource\Pages\ViewMaintenanceLog;
use App\Filament\Clusters\Computers\Resources\MaintenanceLogResource\Tables\MaintenanceLogsTable;
use App\Models\ComputerMaintenanceLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class MaintenanceLogResource extends Resource
{
    protected static ?string $model = ComputerMaintenanceLog::class;

    protected static ?string $cluster = ComputersCluster::class;

    protected static ?string $navigationLabel = 'Maintenance Logs';

    protected static ?string $modelLabel = 'Maintenance Log';

    protected static ?string $slug = 'maintenance-logs';

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function table(Table $table): Table
    {
        return MaintenanceLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceLogs::route('/'),
            'view' => ViewMaintenanceLog::route('/{record}'),
        ];
    }
}
