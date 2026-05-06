<?php

namespace App\Console\Commands;

use App\Models\Computer;
use Illuminate\Console\Command;

class BackfillComputerKioskSlugCommand extends Command
{
    protected $signature = 'computers:backfill-kiosk-slug';

    protected $description = 'Backfill checkin_slug for computer records that were created before the slug column existed.';

    public function handle(): int
    {
        $missing = Computer::query()->whereNull('checkin_slug')->orWhere('checkin_slug', '')->get();

        if ($missing->isEmpty()) {
            $this->info('All computers already have checkin_slug. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$missing->count()} computer(s) missing checkin_slug. Backfilling…");

        foreach ($missing as $computer) {
            $computer->ensureCheckinSlug();
            $this->line("  ✓ {$computer->name} → {$computer->checkin_slug}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
