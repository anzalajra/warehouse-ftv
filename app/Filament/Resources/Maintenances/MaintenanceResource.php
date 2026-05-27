<?php

namespace App\Filament\Resources\Maintenances;

use App\Filament\Resources\Maintenances\Pages\ManageMaintenances;
use App\Models\ProductUnit;
use App\Models\UnitKit;
use App\Models\MaintenanceRecord;
use App\Models\FinanceTransaction;
use App\Models\FinanceAccount;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
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
        return static::getModel()::whereIn('condition', ['lost', 'broken'])->count() ?: null;
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
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serial_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('maintenance_summary')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (!$record) return 'Unknown';
                        
                        $unitCondition = $record->condition;
                        // Check kits - eager loading is handled by Filament usually, or we query
                        $kits = $record->kits; 

                        // 1. Check for Lost/Broken (High Priority)
                        if ($unitCondition === 'lost') return 'Unit Lost';
                        if ($unitCondition === 'broken') return 'Unit Broken';

                        foreach ($kits as $kit) {
                            if ($kit->condition === 'lost') return 'Kit Lost';
                            if ($kit->condition === 'broken') return 'Kit Broken';
                        }

                        // 2. Check for Excellent
                        $unitExcellent = $unitCondition === 'excellent';
                        $kitsExcellent = $kits->every(fn($k) => $k->condition === 'excellent');

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
                        ->whereIn('condition', ['broken', 'lost'])
                        ->orWhere('status', 'maintenance')
                        ->orWhereHas('kits', fn ($q) => $q->whereIn('condition', ['broken', 'lost']))
                    )
                    ->label('Needs Attention (Broken/Lost/Maintenance/Kits)')
                    ->default(),
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
                            ->options([
                                'In Repair' => 'In Repair',
                                'Waiting Parts' => 'Waiting Parts',
                                'Ready for QC' => 'Ready for QC',
                            ])
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
                                    ->options([
                                        'In Repair' => 'In Repair',
                                        'Waiting Parts' => 'Waiting Parts',
                                        'Ready for QC' => 'Ready for QC',
                                    ])
                                    ->label('Maintenance Status'),
                                Textarea::make('notes'),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->columns(2)
                            ->visible(fn ($record) => $record->kits()->exists())
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
                            // Create Maintenance Record
                            $maintenanceRecord = MaintenanceRecord::create([
                                'product_unit_id' => $record->id,
                                'technician_id' => \Illuminate\Support\Facades\Auth::id(),
                                'title' => $data['title'],
                                'description' => $data['notes'],
                                'cost' => $data['cost'],
                                'date' => $data['date'],
                                'status' => 'in_progress',
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
                            ->options([
                                'In Repair' => 'In Repair',
                                'Waiting Parts' => 'Waiting Parts',
                                'Waiting Customer' => 'Waiting Customer',
                                'Ready for QC' => 'Ready for QC',
                            ])
                            ->required(),
                        Textarea::make('notes')
                            ->label('Maintenance Notes'),
                    ])
                    ->action(function (ProductUnit $record, array $data) {
                        $record->update([
                            'maintenance_status' => $data['maintenance_status'],
                            'notes' => $data['notes'] ? $record->notes . "\n[" . now()->format('Y-m-d') . "] " . $data['notes'] : $record->notes,
                        ]);
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
                            $updates = [
                                'maintenance_status' => null, // Clear status
                                'notes' => $record->notes . "\n[RESOLVED " . now()->format('Y-m-d') . "] " . $data['resolution'] . ": " . $data['notes'],
                            ];

                            if ($data['resolution'] === 'Write Off') {
                                $updates['status'] = ProductUnit::STATUS_RETIRED;
                            } else {
                                $updates['status'] = ProductUnit::STATUS_AVAILABLE;
                                $updates['condition'] = $data['condition'];
                                
                                // Close any active maintenance records
                                $suffix = "\n[RESOLVED " . now()->format('Y-m-d') . "] " . $data['resolution'] . ": " . $data['notes'];
                                $record->maintenanceRecords()
                                    ->whereIn('status', ['pending', 'in_progress'])
                                    ->update([
                                        'status' => 'completed',
                                        'description' => DB::raw('CONCAT(COALESCE(description, ' . DB::getPdo()->quote('') . '), ' . DB::getPdo()->quote($suffix) . ')'),
                                    ]);

                                // Update Kits
                                if (isset($data['kit_updates']) && is_array($data['kit_updates'])) {
                                    foreach ($data['kit_updates'] as $kitData) {
                                        if (isset($kitData['id'])) {
                                            // Update kit condition and CLEAR maintenance status
                                            UnitKit::where('id', $kitData['id'])->update([
                                                'condition' => $kitData['condition'],
                                                'maintenance_status' => null
                                            ]);
                                        }
                                    }
                                }
                            }

                            $record->update($updates);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Issue Resolved')
                            ->body('Unit and kits updated successfully.')
                            ->success()
                            ->send();
                    }),
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
