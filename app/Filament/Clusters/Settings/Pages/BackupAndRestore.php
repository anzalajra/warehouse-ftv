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
                            'files' => 'Files & Media (Images, Documents)',
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

    protected function getFileDirectories(): array
    {
        return [
            ['disk' => 'public', 'directory' => 'products'],
            ['disk' => 'public', 'directory' => 'brands'],
            ['disk' => 'public', 'directory' => 'settings'],
            ['disk' => 'public', 'directory' => 'hero-slides'],
            ['disk' => 'local', 'directory' => 'customer-documents'],
        ];
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
            $allTables = Schema::getTableListing(schemaQualified: false);
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

            if (empty($tablesToBackup)) {
                throw new \Exception("No tables found to backup for the selected options. Please check if the required tables exist.");
            }

            Log::channel('backup-restore')->info('Starting backup', [
                'type' => $type,
                'tables' => array_values($tablesToBackup),
            ]);

            $tableCount = 0;
            foreach ($tablesToBackup as $table) {
                $this->progressMessage = "Backing up table: $table";

                $rows = DB::table($table)->get();
                $jsonData = json_encode($rows->toArray(), JSON_PRETTY_PRINT);

                $zip->addFromString("$table.json", $jsonData);
                $tableCount++;
            }

            // Backup files if 'files' or 'full' is selected
            $fileCount = 0;
            $includeFiles = $isFullBackup || in_array('files', $options);

            if ($includeFiles) {
                $this->progressMessage = 'Backing up files & media...';
                $fileDirectories = $this->getFileDirectories();

                foreach ($fileDirectories as $dir) {
                    $disk = Storage::disk($dir['disk']);
                    $files = $disk->allFiles($dir['directory']);

                    foreach ($files as $file) {
                        $fullFilePath = $disk->path($file);
                        if (File::exists($fullFilePath)) {
                            $zip->addFile($fullFilePath, "files/{$dir['disk']}/{$file}");
                            $fileCount++;
                        }
                    }
                }

                // Also backup spatie media library files (numbered directories in public disk)
                $publicDisk = Storage::disk('public');
                $allPublicFiles = $publicDisk->allFiles('.');
                foreach ($allPublicFiles as $file) {
                    // Match numbered directories (1/*, 2/*, etc.) used by media library
                    if (preg_match('#^\d+/#', $file)) {
                        $fullFilePath = $publicDisk->path($file);
                        if (File::exists($fullFilePath)) {
                            $zip->addFile($fullFilePath, "files/public/{$file}");
                            $fileCount++;
                        }
                    }
                }
            }

            $zip->close();

            if (!File::exists($zipPath)) {
                throw new \Exception("Backup file could not be created. The ZIP archive may be empty.");
            }

            BackupHistory::create([
                'user_id' => Auth::id(),
                'type' => $type,
                'filename' => $filename,
                'size' => File::size($zipPath),
                'status' => 'success',
            ]);

            $body = "Backed up $tableCount tables.";
            if ($fileCount > 0) {
                $body .= " Included $fileCount files.";
            }

            Notification::make()
                ->title('Backup Created Successfully')
                ->body($body)
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

                // Strip schema prefix for cross-server compatibility
                // e.g., "kintsidg_1ca1_whftv.customers" → "customers"
                if (str_contains($tableName, '.')) {
                    $tableName = substr($tableName, strrpos($tableName, '.') + 1);
                }

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

            // Restore files from ZIP
            $restoredFiles = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                // Only process files under the "files/" prefix
                if (!str_starts_with($entryName, 'files/')) {
                    continue;
                }

                // Parse: files/{disk}/{path}
                $relativePath = substr($entryName, strlen('files/'));
                $slashPos = strpos($relativePath, '/');
                if ($slashPos === false) {
                    continue;
                }

                $diskName = substr($relativePath, 0, $slashPos);
                $filePath = substr($relativePath, $slashPos + 1);

                // Skip directories (empty file path or trailing slash)
                if (empty($filePath) || str_ends_with($filePath, '/')) {
                    continue;
                }

                $this->progressMessage = "Restoring file: $filePath";

                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    try {
                        Storage::disk($diskName)->put($filePath, $content);
                        $restoredFiles++;
                    } catch (\Exception $e) {
                        Log::warning("Could not restore file $filePath to $diskName disk: " . $e->getMessage());
                    }
                }
            }

            $zip->close();

            // Cleanup uploaded file
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }

            $body = "Restored $restoredCount tables.";
            if ($restoredFiles > 0) {
                $body .= " Restored $restoredFiles files.";
            }
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
