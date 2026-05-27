<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Helper to attach a friendly "cannot delete: still in use" guard to a
 * Filament DeleteAction. Used for records protected by an ON DELETE RESTRICT
 * foreign key, where letting the DB raise a QueryException would surface
 * an ugly red error toast to admins.
 *
 * Usage in a Resource table:
 *
 *     DeleteAction::make()
 *         ->before(PreventDeleteIfUsed::guard([
 *             'transaksi jurnal' => fn ($record) => $record->journalEntryItems()->count(),
 *             'mapping kategori' => fn ($record) => $record->categoryMappings()->count(),
 *         ])),
 */
class PreventDeleteIfUsed
{
    /**
     * @param  array<string, callable(Model): int>  $checks  label => count-resolver
     */
    public static function guard(array $checks): \Closure
    {
        return function (DeleteAction $action, Model $record) use ($checks) {
            $blockers = Collection::make($checks)
                ->map(fn (callable $resolver, string $label) => [
                    'label' => $label,
                    'count' => (int) $resolver($record),
                ])
                ->filter(fn (array $row) => $row['count'] > 0);

            if ($blockers->isEmpty()) {
                return;
            }

            $detail = $blockers
                ->map(fn (array $row) => "{$row['count']} {$row['label']}")
                ->join(', ', ' dan ');

            Notification::make()
                ->title('Tidak bisa dihapus')
                ->body("Record ini masih digunakan oleh {$detail}. Hapus / arsipkan referensi tersebut terlebih dahulu, atau nonaktifkan record ini saja.")
                ->danger()
                ->persistent()
                ->send();

            $action->halt();
        };
    }
}
