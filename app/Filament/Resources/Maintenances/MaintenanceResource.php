<?php

namespace App\Filament\Resources\Maintenances;

use App\Filament\Resources\Maintenances\Pages\ManageMaintenances;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\MaintenanceRecord;
use App\Models\ProductUnit;
use App\Models\UnitKit;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MaintenanceResource extends Resource
{
    protected static ?string $model = ProductUnit::class;

    protected static ?string $label = 'Maintenance';

    protected static ?string $pluralLabel = 'Maintenance & QC';
    // protected static ?string $navigationGroup = 'Inventory';
    // protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationBadge(): ?string
    {
        // Match the default "Aktif" tab scope so the badge equals the rows shown.
        return static::getModel()::query()
            ->where(fn (Builder $q) => $q
                ->whereIn('condition', ['broken', 'lost'])
                ->orWhere('status', ProductUnit::STATUS_MAINTENANCE))
            ->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only info
                TextInput::make('serial_number')
                    ->disabled(),
                TextInput::make('status')
                    ->disabled(),
                TextInput::make('condition')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['warehouse', 'kits'])
                ->withCount(['maintenanceRecords as open_tickets_count' => fn (Builder $q) => $q->whereNull('unit_kit_id')->open()])
                ->withSum('maintenanceRecords as maintenance_cost_total', 'cost'))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('maintenance_summary')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (! $record) {
                            return 'Unknown';
                        }

                        $unitCondition = $record->condition;
                        // Check kits - eager loading is handled by Filament usually, or we query
                        $kits = $record->kits;

                        // 1. Check for Lost/Broken (High Priority)
                        if ($unitCondition === 'lost') {
                            return 'Unit Lost';
                        }
                        if ($unitCondition === 'broken') {
                            return 'Unit Broken';
                        }

                        foreach ($kits as $kit) {
                            if ($kit->condition === 'lost') {
                                return 'Kit Lost';
                            }
                            if ($kit->condition === 'broken') {
                                return 'Kit Broken';
                            }
                        }

                        // 2. Check for Excellent
                        $unitExcellent = $unitCondition === 'excellent';
                        $kitsExcellent = $kits->every(fn ($k) => $k->condition === 'excellent');

                        if ($unitExcellent && $kitsExcellent) {
                            return 'Excellent';
                        }

                        // 3. Fallback to Good
                        return 'Good';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Excellent' => 'success',
                        'Good' => 'info',
                        'Unit Lost', 'Unit Broken', 'Kit Lost', 'Kit Broken' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('maintenance_status')
                    ->label('Maintenance Progress')
                    ->badge()
                    ->color('warning')
                    ->placeholder('-')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('days_in_maintenance')
                    ->label('Lama')
                    ->badge()
                    ->state(fn ($record) => $record->days_in_maintenance !== null ? $record->days_in_maintenance.' hari' : null)
                    ->color(fn ($record) => ($record->days_in_maintenance ?? 0) > (int) \App\Models\Setting::get('maintenance_overdue_days', 7) ? 'danger' : 'warning')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('open_tickets_count')
                    ->label('Tiket')
                    ->badge()
                    ->color('warning')
                    ->placeholder('0')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('maintenance_cost_total')
                    ->label('Total Biaya')
                    ->state(fn ($record) => (float) ($record->maintenance_cost_total ?? 0))
                    ->money('IDR')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('technician')
                    ->label('Teknisi')
                    ->state(fn ($record) => $record->open_maintenance_record?->technician?->name)
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('xl'),
                TextColumn::make('source_rental')
                    ->label('Dari Rental')
                    ->state(fn ($record) => $record->open_maintenance_record?->rental?->rental_code)
                    ->description(fn ($record) => ($name = $record->open_maintenance_record?->rental?->customer?->name)
                        ? 'Customer: '.$name
                        : null)
                    ->url(fn ($record) => ($rental = $record->open_maintenance_record?->rental)
                        ? \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $rental->id])
                        : null)
                    ->color('primary')
                    ->placeholder('—')
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('last_checked_at')
                    ->label('Last QC')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('condition')
                    ->options(ProductUnit::getConditionOptions()),
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->options(ProductUnit::getStatusOptions()),
                \Filament\Tables\Filters\Filter::make('needs_attention')
                    ->query(fn (Builder $query) => $query
                        ->where(fn (Builder $q) => $q
                            ->whereIn('condition', ['broken', 'lost'])
                            ->orWhere('status', 'maintenance')
                            ->orWhereHas('kits', fn ($q) => $q->whereIn('condition', ['broken', 'lost'])))
                    )
                    ->label('Needs Attention (Broken/Lost/Maintenance/Kits)'),
            ])
            ->recordActions([
                EditAction::make('manage')
                    ->label('Manage')
                    ->icon('heroicon-o-wrench')
                    ->color('warning')
                    ->modalHeading('Manage Unit & Kits')
                    ->form([
                        // Unit Fields
                        Select::make('condition')
                            ->label('Unit Condition')
                            ->options(ProductUnit::getConditionOptions())
                            ->required(),
                        Select::make('maintenance_status')
                            ->label('Unit Maintenance Status')
                            ->options(ProductUnit::getMaintenanceStatusOptions())
                            ->placeholder('Select Status'),
                        Textarea::make('notes')
                            ->label('Unit Notes'),

                        // Kits Repeater
                        Repeater::make('kits')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')->disabled(),
                                TextInput::make('serial_number')->disabled(),
                                Select::make('condition')
                                    ->options(UnitKit::getConditionOptions())
                                    ->required(),
                                Select::make('maintenance_status')
                                    ->options(ProductUnit::getMaintenanceStatusOptions())
                                    ->label('Maintenance Status'),
                                Textarea::make('notes'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->columns(2)
                            ->visible(fn ($record) => $record->kits()->exists()),
                    ]),

                \Filament\Actions\Action::make('quick_check')
                    ->label('QC Passed')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->action(function (ProductUnit $record) {
                        $record->update([
                            'last_checked_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Stock Opname Recorded')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('record_expense')
                    ->label('Record Cost')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('danger')
                    ->visible(fn ($record) => $record && (
                        in_array($record->condition, ['broken', 'lost']) ||
                        $record->status === 'maintenance' ||
                        $record->kits()->whereIn('condition', ['broken', 'lost'])->exists()
                    ))
                    ->form([
                        TextInput::make('title')
                            ->label('Expense Title')
                            ->placeholder('e.g. Sparepart Replacement')
                            ->required(),
                        TextInput::make('cost')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Select::make('finance_account_id')
                            ->label('Source Account')
                            ->options(FinanceAccount::pluck('name', 'id'))
                            ->required(),
                        DatePicker::make('date')
                            ->default(now())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes'),
                    ])
                    ->action(function (ProductUnit $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            // Create Maintenance Record (open work item that keeps the unit in maintenance)
                            $maintenanceRecord = MaintenanceRecord::create([
                                'product_unit_id' => $record->id,
                                'technician_id' => \Illuminate\Support\Facades\Auth::id(),
                                'title' => $data['title'],
                                'description' => $data['notes'],
                                'cost' => $data['cost'],
                                'date' => $data['date'],
                                'started_at' => now(),
                                'status' => MaintenanceRecord::STATUS_IN_PROGRESS,
                                'type' => MaintenanceRecord::TYPE_CORRECTIVE,
                            ]);

                            // Create Finance Transaction
                            FinanceTransaction::create([
                                'finance_account_id' => $data['finance_account_id'],
                                'user_id' => \Illuminate\Support\Facades\Auth::id(),
                                'type' => FinanceTransaction::TYPE_EXPENSE,
                                'amount' => $data['cost'],
                                'date' => $data['date'],
                                'category' => 'Maintenance',
                                'description' => "Maintenance Cost: {$record->product->name} ({$record->serial_number}) - {$data['title']}",
                                'reference_type' => MaintenanceRecord::class,
                                'reference_id' => $maintenanceRecord->id,
                            ]);
                        });

                        // Open ticket now exists → ensure the unit reflects MAINTENANCE.
                        $record->refreshStatus();

                        \Filament\Notifications\Notification::make()
                            ->title('Expense Recorded')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('update_progress')
                    ->label('Update Progress')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->visible(fn ($record) => $record && (
                        in_array($record->condition, ['broken', 'lost']) ||
                        $record->status === 'maintenance' ||
                        $record->kits()->whereIn('condition', ['broken', 'lost'])->exists()
                    ))
                    ->form([
                        Select::make('maintenance_status')
                            ->options(ProductUnit::getMaintenanceStatusOptions())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Maintenance Notes'),
                    ])
                    ->action(function (ProductUnit $record, array $data) {
                        $record->update([
                            'maintenance_status' => $data['maintenance_status'],
                            'notes' => $data['notes'] ? $record->notes."\n[".now()->format('Y-m-d').'] '.$data['notes'] : $record->notes,
                        ]);

                        // Keep the open unit-level ticket marked as actively worked on.
                        $record->maintenanceRecords()
                            ->whereNull('unit_kit_id')
                            ->open()
                            ->update(['status' => MaintenanceRecord::STATUS_IN_PROGRESS]);
                    }),

                \Filament\Actions\Action::make('resolve_issue')
                    ->label('Resolve Issue')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record && (
                        in_array($record->condition, ['broken', 'lost']) ||
                        $record->status === 'maintenance' ||
                        $record->kits()->whereIn('condition', ['broken', 'lost'])->exists()
                    ))
                    ->mountUsing(function ($form, $record) {
                        $form->fill([
                            'kit_updates' => $record->kits->map(function ($kit) {
                                return [
                                    'id' => $kit->id,
                                    'name' => $kit->name,
                                    'condition' => $kit->condition,
                                ];
                            })->toArray(),
                        ]);
                    })
                    ->form([
                        Select::make('resolution')
                            ->label('Action Taken')
                            ->options([
                                'Repaired' => 'Repaired (Service)',
                                'Replaced' => 'Replaced (New Unit)',
                                'Found' => 'Found (Was Lost)',
                                'Write Off' => 'Write Off (Retired)',
                            ])
                            ->required()
                            ->live(),
                        Select::make('condition')
                            ->label('Final Unit Condition')
                            ->options([
                                'excellent' => 'Excellent',
                                'good' => 'Good',
                                'fair' => 'Fair',
                            ])
                            ->required()
                            ->hidden(fn ($get) => $get('resolution') === 'Write Off'),

                        Repeater::make('kit_updates')
                            ->label('Kit Final Conditions')
                            ->schema([
                                TextInput::make('name')->disabled(),
                                Select::make('condition')
                                    ->options(UnitKit::getConditionOptions())
                                    ->required(),
                                Hidden::make('id'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->columns(2)
                            ->visible(fn ($record) => $record->kits()->exists())
                            ->hidden(fn ($get) => $get('resolution') === 'Write Off'),

                        Textarea::make('notes')
                            ->label('Resolution Notes')
                            ->required(),
                    ])
                    ->action(function (ProductUnit $record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $isWriteOff = $data['resolution'] === 'Write Off';
                            $suffix = "\n[RESOLVED ".now()->format('Y-m-d').'] '.$data['resolution'].': '.$data['notes'];

                            $updates = [
                                'maintenance_status' => null, // Clear progress label
                                'notes' => $record->notes.$suffix,
                            ];

                            if (! $isWriteOff) {
                                $updates['condition'] = $data['condition'];
                            }

                            // Close every open ticket (unit + kit level) and stamp turnaround.
                            $record->maintenanceRecords()
                                ->open()
                                ->update([
                                    'status' => MaintenanceRecord::STATUS_COMPLETED,
                                    'completed_at' => now(),
                                    'description' => DB::raw('CONCAT(COALESCE(description, '.DB::getPdo()->quote('').'), '.DB::getPdo()->quote($suffix).')'),
                                ]);

                            // Update kits (kits ride along on a write-off, so skip there).
                            if (! $isWriteOff && isset($data['kit_updates']) && is_array($data['kit_updates'])) {
                                foreach ($data['kit_updates'] as $kitData) {
                                    if (isset($kitData['id'])) {
                                        UnitKit::where('id', $kitData['id'])->update([
                                            'condition' => $kitData['condition'],
                                            'maintenance_status' => null,
                                        ]);
                                    }
                                }
                            }

                            $record->update($updates);

                            // Recompute from the now-clean state: condition fixed + tickets closed.
                            if ($isWriteOff) {
                                $record->update(['status' => ProductUnit::STATUS_RETIRED]);
                            } else {
                                $record->refreshStatus();
                            }
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Issue Resolved')
                            ->body('Unit and kits updated successfully.')
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\Action::make('history')
                    ->label('Riwayat')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading('Riwayat Maintenance')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (ProductUnit $record) => view(
                        'filament.resources.maintenances.history-modal',
                        ['records' => $record->maintenanceRecords()->with(['technician', 'unitKit', 'rental.customer'])->latest('id')->get()],
                    )),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMaintenances::route('/'),
        ];
    }
}
