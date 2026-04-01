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
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
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
                    ->action(function (BackupHistory $record) {
                        return response()->download(storage_path('app/backups/' . $record->filename));
                    })
                    ->visible(fn (BackupHistory $record) => Storage::exists('backups/' . $record->filename)),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (BackupHistory $record) {
                        if (Storage::exists('backups/' . $record->filename)) {
                            Storage::delete('backups/' . $record->filename);
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

    public function processBackup(array $options)
    {
        $this->isProcessing = true;
        $this->currentOperation = 'Creating Backup';
        $this->progressMessage = 'Initializing...';
        
        try {
            $filename = 'backup-' . Carbon::now()->format('Y-m-d-H-i-s') . '.zip';
            $zipPath = storage_path('app/backups/' . $filename);
            
            if (!File::exists(dirname($zipPath))) {
                File::makeDirectory(dirname($zipPath), 0755, true);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \Exception("Cannot create zip file");
            }

            // Get all tables
            $tables = Schema::getTableListing();
            $excludeTables = ['migrations', 'backup_histories', 'jobs', 'failed_jobs', 'sessions', 'cache', 'cache_locks', 'job_batches'];

            foreach ($tables as $table) {
                if (in_array($table, $excludeTables)) {
                    continue;
                }

                $this->progressMessage = "Backing up table: $table";
                
                // Get data
                $rows = DB::table($table)->get();
                $jsonData = json_encode($rows->toArray(), JSON_PRETTY_PRINT);
                
                $zip->addFromString("$table.json", $jsonData);
            }

            $zip->close();

            // Record history
            BackupHistory::create([
                'user_id' => Auth::id(),
                'type' => 'manual',
                'filename' => $filename,
                'size' => File::size($zipPath),
                'status' => 'success',
            ]);

            Notification::make()
                ->title('Backup Created Successfully')
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
                'type' => 'manual',
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
        
        try {
            // Check if path exists
            if (!Storage::disk('local')->exists($backupFile)) {
                throw new \Exception("Backup file not found: " . $backupFile);
            }
            $fullPath = Storage::disk('local')->path($backupFile);

            $zip = new ZipArchive();
            if ($zip->open($fullPath) !== true) {
                throw new \Exception("Cannot open zip file");
            }

            // Disable foreign keys
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Iterate through zip files
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $tableName = pathinfo($filename, PATHINFO_FILENAME);
                
                $this->progressMessage = "Restoring table: $tableName";
                
                // Read JSON
                $json = $zip->getFromIndex($i);
                $data = json_decode($json, true);
                
                if (is_array($data)) {
                    // Truncate table
                    try {
                        DB::table($tableName)->truncate();
                        
                        // Insert in chunks
                        foreach (array_chunk($data, 100) as $chunk) {
                            DB::table($tableName)->insert($chunk);
                        }
                    } catch (\Exception $e) {
                        // Table might not exist or other error
                        \Illuminate\Support\Facades\Log::warning("Could not restore table $tableName: " . $e->getMessage());
                    }
                }
            }

            // Enable foreign keys
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $zip->close();
            
            // Cleanup
            Storage::disk('local')->delete($backupFile);

            Notification::make()
                ->title('Restore Completed Successfully')
                ->success()
                ->send();
            
            // Reload page
            return redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            Notification::make()
                ->title('Restore Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isProcessing = false;
        }
    }
}
