<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Models\User;
use App\Notifications\RecurringRentalGeneratedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GenerateRecurringRentals extends Command
{
    protected $signature = 'rentals:generate-recurring {--dry-run : List what would be generated without writing}';

    protected $description = 'Generate draft quotations from recurring rentals whose next cycle is due';

    public function handle(): int
    {
        $today = now()->toDateString();
        $dryRun = (bool) $this->option('dry-run');

        $sources = Rental::query()
            ->where('is_recurring', true)
            ->whereNotNull('recurrence_next_date')
            ->whereDate('recurrence_next_date', '<=', $today)
            ->where(function ($q) {
                $q->whereNull('recurrence_end_date')
                    ->orWhereColumn('recurrence_end_date', '>=', 'recurrence_next_date');
            })
            ->with('items')
            ->get();

        if ($sources->isEmpty()) {
            $this->info('No recurring rentals due.');

            return self::SUCCESS;
        }

        $admins = User::role(['super_admin', 'admin', 'staff'])->get();
        $generated = 0;

        foreach ($sources as $source) {
            $interval = $source->recurrence_interval === 'weekly' ? 'weekly' : 'monthly';
            $current = Carbon::parse($source->recurrence_next_date);
            $next = $interval === 'weekly' ? $current->copy()->addWeek() : $current->copy()->addMonth();

            if ($dryRun) {
                $this->line("[dry-run] {$source->rental_code}: quote for {$current->toDateString()}, next → {$next->toDateString()}");

                continue;
            }

            try {
                $child = $source->replicateForRecurrence();
                $generated++;

                // Advance the schedule; retire the source once we pass the end date.
                $updates = ['recurrence_next_date' => $next->toDateString()];
                if ($source->recurrence_end_date && $next->gt(Carbon::parse($source->recurrence_end_date))) {
                    $updates['is_recurring'] = false;
                }
                $source->updateQuietly($updates);

                $source->logActivity(
                    "Recurring: generated quotation {$child->rental_code} for {$current->toDateString()}",
                    'general',
                    'system',
                );

                if ($admins->isNotEmpty()) {
                    Notification::send($admins, new RecurringRentalGeneratedNotification($child, $source));
                }

                $this->info("Generated {$child->rental_code} from {$source->rental_code}");
                Log::info('Recurring rental generated', [
                    'source' => $source->id,
                    'child' => $child->id,
                    'for' => $current->toDateString(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Recurring rental generation failed', [
                    'source' => $source->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed on {$source->rental_code}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$generated} quotation(s) generated.");

        return self::SUCCESS;
    }
}
