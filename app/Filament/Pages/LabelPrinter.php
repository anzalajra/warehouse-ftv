<?php

namespace App\Filament\Pages;

use App\Models\ProductUnit;
use App\Services\UnitCodeService;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Bluetooth label print studio. Wraps the standalone LuckPrinter ES modules
 * (public/vendor/luckprinter/) in a Filament page: design a label (text + QR +
 * image) and print it to a Luck Jingle Bluetooth printer via Web Bluetooth.
 *
 * Can be opened standalone (manual designer) or deep-linked from a product unit
 * with a prefilled print queue:
 *   ?unit={id}        single unit + its serial-bearing kits
 *   ?units=1,2,3      bulk: many units merged into one queue
 *
 * QR payloads are encoded server-side as PREFIX:serial (UnitCodeService) so the
 * printed labels read identically to the server PNGs and stay compatible with
 * the Pickup/Return unit scanner.
 */
class LabelPrinter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Print Label';

    protected static ?string $title = 'Print Label';

    protected static ?string $slug = 'label-printer';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.label-printer';

    /** Prefilled print queue: list of { serial, name, payload }. */
    public array $queue = [];

    public function mount(): void
    {
        $codes = app(UnitCodeService::class);

        $ids = $this->resolveUnitIds();

        if (empty($ids)) {
            return;
        }

        $units = ProductUnit::with(['kits', 'product'])
            ->whereIn('id', $ids)
            ->get();

        // Preserve the order the ids arrived in (so bulk selection prints in order).
        $units = $units->sortBy(fn ($u) => array_search($u->id, $ids))->values();

        foreach ($units as $unit) {
            $this->pushQueueItem($codes, $unit->serial_number, $unit->product->name ?? 'Unit');

            foreach ($unit->kits as $kit) {
                if (filled($kit->serial_number)) {
                    $this->pushQueueItem($codes, $kit->serial_number, $kit->name);
                }
            }
        }
    }

    /** Parse ?unit= (single) and ?units= (comma-separated) into a unique id list. */
    protected function resolveUnitIds(): array
    {
        $ids = [];

        if (filled($single = request()->query('unit'))) {
            $ids[] = (int) $single;
        }

        if (filled($many = request()->query('units'))) {
            foreach (explode(',', (string) $many) as $part) {
                if (($id = (int) trim($part)) > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function pushQueueItem(UnitCodeService $codes, ?string $serial, string $name): void
    {
        if (blank($serial)) {
            return;
        }

        $this->queue[] = [
            'serial' => $serial,
            'name' => $name,
            'payload' => $codes->encode($serial),
        ];
    }
}
