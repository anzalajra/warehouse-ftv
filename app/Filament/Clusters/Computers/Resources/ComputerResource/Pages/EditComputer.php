<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Pages;

use App\Filament\Clusters\Computers\Resources\ComputerResource;
use App\Models\Computer;
use App\Models\KioskPairingCode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditComputer extends EditRecord
{
    protected static string $resource = ComputerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkinPage')
                ->label('Check-in Page')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn () => $this->record->checkinUrl())
                ->openUrlInNewTab(),
            ActionGroup::make([
                Action::make('pairKiosk')
                    ->label(fn () => $this->record->kiosk_token ? 'Re-pair Kiosk App' : 'Pair Kiosk App')
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->modalHeading(fn () => $this->record->kiosk_token ? 'Re-pair Kiosk App' : 'Pair Kiosk App')
                    ->modalDescription(fn () => $this->record->kiosk_token
                        ? 'Token kiosk yang lama akan dibatalkan. Aplikasi kiosk di komputer ini harus di-pairing ulang.'
                        : 'Buka aplikasi Warehouse FTV Kiosk di komputer ini, lalu masukkan kode di bawah dalam 5 menit.')
                    ->action(function () {
                        if ($this->record->kiosk_token) {
                            $this->record->kiosk_token = null;
                            $this->record->kiosk_paired_at = null;
                            $this->record->save();
                        }

                        $code = KioskPairingCode::generateUnique($this->record->id);

                        Notification::make()
                            ->title('Pairing code: '.$code->code)
                            ->body('Berlaku hingga '.$code->expires_at->format('H:i').'. Masukkan ke aplikasi kiosk di komputer ini.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('showKioskStatus')
                    ->label('Show Kiosk Status')
                    ->icon('heroicon-o-signal')
                    ->color('gray')
                    ->visible(fn () => (bool) $this->record->kiosk_token)
                    ->modalHeading('Kiosk Status')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function () {
                        $c = $this->record;
                        $data = $c->last_heartbeat_data ?? [];
                        $apps = $data['running_apps'] ?? [];
                        $appsHtml = empty($apps)
                            ? '<em class="text-gray-400">— tidak ada</em>'
                            : '<ul class="list-disc pl-5">'.implode('', array_map(fn ($a) => '<li>'.e($a).'</li>', $apps)).'</ul>';
                        $online = $c->is_online ? '<span class="text-green-600 font-semibold">Online</span>' : '<span class="text-gray-500">Offline</span>';
                        $lastSeen = $c->last_seen_at?->diffForHumans() ?? '—';
                        $version = e($data['app_version'] ?? '—');
                        $uptime = isset($data['uptime_seconds']) ? gmdate('H:i:s', (int) $data['uptime_seconds']) : '—';
                        $paired = $c->kiosk_paired_at?->format('d M Y H:i') ?? '—';

                        return new \Illuminate\Support\HtmlString(<<<HTML
<dl class="grid grid-cols-1 gap-3 text-sm">
    <div><dt class="text-gray-500">Status</dt><dd>{$online}</dd></div>
    <div><dt class="text-gray-500">Last seen</dt><dd>{$lastSeen}</dd></div>
    <div><dt class="text-gray-500">App version</dt><dd>{$version}</dd></div>
    <div><dt class="text-gray-500">Uptime</dt><dd>{$uptime}</dd></div>
    <div><dt class="text-gray-500">Paired sejak</dt><dd>{$paired}</dd></div>
    <div><dt class="text-gray-500">Aplikasi berjalan</dt><dd>{$appsHtml}</dd></div>
</dl>
HTML);
                    }),
            ])
                ->label('Kiosk App')
                ->icon('heroicon-o-cpu-chip')
                ->color('primary')
                ->button(),
            Action::make('toggleMaintenance')
                ->label(fn () => $this->record->status === Computer::STATUS_MAINTENANCE ? 'End Maintenance' : 'Set Maintenance')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->form(function () {
                    if ($this->record->status === Computer::STATUS_MAINTENANCE) {
                        return [];
                    }

                    return [
                        Textarea::make('reason')
                            ->required()
                            ->placeholder('Alasan masuk maintenance (e.g. Install ulang Adobe, Ganti RAM)'),
                    ];
                })
                ->action(function (array $data) {
                    if ($this->record->status === Computer::STATUS_MAINTENANCE) {
                        $this->record->status = Computer::STATUS_AVAILABLE;
                        $this->record->save();
                    } else {
                        $this->record->maintenance_reason = $data['reason'] ?? 'Maintenance';
                        $this->record->status = Computer::STATUS_MAINTENANCE;
                        $this->record->save();
                    }
                })
                ->requiresConfirmation(),
            DeleteAction::make(),
        ];
    }
}
