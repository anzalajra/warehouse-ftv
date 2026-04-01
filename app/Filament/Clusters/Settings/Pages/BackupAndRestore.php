<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\BackupHistory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Carbon\Carbon;

class BackupAndRestore extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = SettingsCluster::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';
    protected static ?string $navigationLabel = 'Backup & Restore';
    protected static ?int $navigationSort = 10;
    protected string $view = 'filament.clusters.settings.pages.backup-and-restore';

    // Progress tracking properties
    public bool $isProcessing = false;
    public string $currentOperation = '';
    public string $progressMessage = '';
    public int $progressPercent = 0;

    public function table(Table $table): Table
    {
        return $table
            ->query(BackupHistory::query()->latest())
            ->columns([
                TextColumn::make('created_at')->dateTime()->label('Date'),
                TextColumn::make('user.name')->label('User'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('filename'),
                TextColumn::make('size')->formatStateUsing(fn ($state) => number_format($state / 1024, 2) . ' KB'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (BackupHistory $record) => route('backup.download', $record))
                    ->visible(fn (BackupHistory $record) => File::exists(storage_path('app/backups/' . $record->filename))),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (BackupHistory $record) {
                        $path = storage_path('app/backups/' . $record->filename);
                        if (File::exists($path)) {
                            File::delete($path);
                        }
                        $record->delete();
                        Notification::make()->title('Backup deleted')->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backup')
                ->label('Create Backup')
                ->modalHeading('Select Data to Backup')
                ->disabled(fn() => $this->isProcessing)
                ->form([
                    CheckboxList::make('options')
                        ->options([
                            'full' => 'Full Backup (Recommended)',
                            'products' => 'Products & Categories',
                            'customers' => 'Customers',
                            'rentals' => 'Rentals',
                            'finance' => 'Finance & Invoices',
                            'settings' => 'Settings & CMS',
                        ])
                        ->default(['full'])
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->processBackup($data['options']);
                }),
                
            Action::make('restore')
                ->label('Restore Backup')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->disabled(fn() => $this->isProcessing)
                ->form([
                    FileUpload::make('backup_file')
                        ->label('Upload Backup File (ZIP)')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->disk('local')
                        ->directory('backups')
                        ->required(),
                ])
                ->action(function (array $data) {
                    return $this->processRestore($data['backup_file']);
                }),
        ];
    }

    protected function getTableCategories(): array
    {
        return [
            'products' => ['products', 'categories', 'brands', 'product_units', 'product_variations', 'unit_kits', 'product_components', 'warehouses'],
            'customers' => ['customers', 'customer_categories', 'customer_documents', 'document_types', 'customer_category_document_type'],
            'rentals' => ['rentals', 'rental_items', 'rental_item_kits'],
            'finance' => ['finance_accounts', 'finance_transactions', 'bills', 'quotations', 'invoices', 'depreciation_runs', 'accounts', 'category_mappings'],
            'settings' => ['settings', 'posts', 'navigations', 'navigation_menus', 'media', 'permissions'],
        ];
    }

    protected function getExcludedTables(): array
    {
        return ['migrations', 'backup_histories', 'jobs', 'failed_jobs', 'sessions', 'cache', 'cache_locks', 'job_batches'];
    }

    public function processBackup(array $options)
    {
        $this->isProcessing = true;
        $this->currentOperation = 'Creating Backup';
        $this->progressMessage = 'Initializing...';

        try {
            $isFullBackup = in_array('full', $options);
            $type = $isFullBackup ? 'full' : implode(', ', $options);
            $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.zip';
            $zipPath = storage_path('app/backups/' . $filename);

            if (!File::exists(dirname($zipPath))) {
                File::makeDirectory(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Cannot create zip file");
            }

            // Determine which tables to backup
            $allTables = Schema::getTableListing();
            $excludeTables = $this->getExcludedTables();

            if ($isFullBackup) {
                $tablesToBackup = array_diff($allTables, $excludeTables);
            } else {
                $categories = $this->getTableCategories();
                $tablesToBackup = [];
                foreach ($options as $option) {
                    if (isset($categories[$option])) {
                        $tablesToBackup = array_merge($tablesToBackup, $categories[$option]);
                    }
                }
                // Only include tables that actually exist
                $tablesToBackup = array_intersect($tablesToBackup, $allTables);
            }

            $tableCount = 0;
            foreach ($tablesToBackup as $table) {
                $this->progressMessage = "Backing up table: $table";

                $rows = DB::table($table)->get();
                $jsonData = json_encode($rows->toArray(), JSON_PRETTY_PRINT);

                $zip->addFromString("$table.json", $jsonData);
                $tableCount++;
            }

            $zip->close();

            BackupHistory::create([
                'user_id' => Auth::id(),
                'type' => $type,
                'filename' => $filename,
                'size' => File::size($zipPath),
                'status' => 'success',
            ]);

            Notification::make()
                ->title('Backup Created Successfully')
                ->body("Backed up $tableCount tables.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Backup Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            BackupHistory::create([
                'user_id' => Auth::id(),
                'type' => $type ?? 'unknown',
                'filename' => $filename ?? 'failed.zip',
                'size' => 0,
                'status' => 'failed',
            ]);
        } finally {
            $this->isProcessing = false;
        }
    }

    public function processRestore(string $backupFile)
    {
        $this->isProcessing = true;
        $this->currentOperation = 'Restoring Backup';

        $driver = DB::connection()->getDriverName();

        try {
            $fullPath = Storage::disk('local')->path($backupFile);

            if (!File::exists($fullPath)) {
                throw new \Exception("Backup file not found: " . $backupFile);
            }

            $zip = new ZipArchive();
            if ($zip->open($fullPath) !== true) {
                throw new \Exception("Cannot open zip file");
            }

            // Disable foreign key checks based on DB driver
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            } elseif ($driver === 'pgsql') {
                DB::statement("SET session_replication_role = 'replica';");
            }

            $restoredCount = 0;
            $skippedTables = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                // Skip non-JSON files and directories
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
                    continue;
                }

                $tableName = pathinfo($filename, PATHINFO_FILENAME);
                $this->progressMessage = "Restoring table: $tableName";

                $json = $zip->getFromIndex($i);
                $data = json_decode($json, true);

                if (!is_array($data)) {
                    $skippedTables[] = $tableName;
                    continue;
                }

                // Check if table exists in database
                if (!Schema::hasTable($tableName)) {
                    $skippedTables[] = $tableName;
                    Log::warning("Restore skipped: table '$tableName' does not exist.");
                    continue;
                }

                try {
                    DB::table($tableName)->truncate();

                    if (count($data) > 0) {
                        foreach (array_chunk($data, 100) as $chunk) {
                            DB::table($tableName)->insert($chunk);
                        }
                    }

                    $restoredCount++;
                } catch (\Exception $e) {
                    $skippedTables[] = $tableName;
                    Log::warning("Could not restore table $tableName: " . $e->getMessage());
                }
            }

            $zip->close();

            // Cleanup uploaded file
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }

            $body = "Restored $restoredCount tables.";
            if (count($skippedTables) > 0) {
                $body .= " Skipped: " . implode(', ', $skippedTables);
            }

            Notification::make()
                ->title('Restore Completed Successfully')
                ->body($body)
                ->success()
                ->send();

            return redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->title('Restore Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            // Re-enable foreign key checks
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } elseif ($driver === 'pgsql') {
                DB::statement("SET session_replication_role = 'DEFAULT';");
            }

            $this->isProcessing = false;
        }
    }
}
