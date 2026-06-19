<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckLateRentals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rentals:check-late 
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and update rental statuses for late pickups and late returns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for late rentals...');
        
        $now = now();
        $isDryRun = $this->option('dry-run');

        // Count rentals that will be affected.
        // Unconfirmed quotations past their pickup date expire (dead-end); only a
        // *confirmed* booking past its pickup date becomes a late pickup.
        $expiredCount = Rental::where('status', Rental::STATUS_QUOTATION)
            ->where('start_date', '<', $now)
            ->count();

        $latePickupsCount = Rental::where('status', Rental::STATUS_CONFIRMED)
            ->where('start_date', '<', $now)
            ->count();

        $lateReturnsCount = Rental::where('status', Rental::STATUS_ACTIVE)
            ->where('end_date', '<', $now)
            ->count();

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Display what will be updated
        $this->table(
            ['Status Change', 'Count'],
            [
                ['Quotation → Expired', $expiredCount],
                ['Confirmed → Late Pickup', $latePickupsCount],
                ['Active → Late Return', $lateReturnsCount],
            ]
        );

        if ($isDryRun) {
            // Show details of affected rentals
            if ($expiredCount > 0) {
                $this->newLine();
                $this->info('Expired Quotations:');
                $expired = Rental::with('customer')
                    ->where('status', Rental::STATUS_QUOTATION)
                    ->where('start_date', '<', $now)
                    ->get();

                foreach ($expired as $rental) {
                    $this->line("  - {$rental->rental_code} | Customer: {$rental->customer->name} | Start: {$rental->start_date->format('Y-m-d H:i')}");
                }
            }

            if ($latePickupsCount > 0) {
                $this->newLine();
                $this->info('Late Pickup Rentals:');
                $latePickups = Rental::with('customer')
                    ->where('status', Rental::STATUS_CONFIRMED)
                    ->where('start_date', '<', $now)
                    ->get();

                foreach ($latePickups as $rental) {
                    $this->line("  - {$rental->rental_code} | Customer: {$rental->customer->name} | Start: {$rental->start_date->format('Y-m-d H:i')}");
                }
            }

            if ($lateReturnsCount > 0) {
                $this->newLine();
                $this->info('Late Return Rentals:');
                $lateReturns = Rental::with('customer')
                    ->where('status', Rental::STATUS_ACTIVE)
                    ->where('end_date', '<', $now)
                    ->get();
                
                foreach ($lateReturns as $rental) {
                    $this->line("  - {$rental->rental_code} | Customer: {$rental->customer->name} | End: {$rental->end_date->format('Y-m-d H:i')}");
                }
            }

            $this->newLine();
            $this->info('Run without --dry-run to apply changes.');
            
            return Command::SUCCESS;
        }

        // Perform the actual updates
        try {
            DB::beginTransaction();

            // Expire unconfirmed quotations whose pickup date has passed
            $updatedExpired = DB::table('rentals')
                ->where('status', Rental::STATUS_QUOTATION)
                ->where('start_date', '<', $now)
                ->update([
                    'status' => Rental::STATUS_EXPIRED,
                    'updated_at' => $now,
                ]);

            // Update late pickups (confirmed bookings past their pickup date)
            $updatedPickups = DB::table('rentals')
                ->where('status', Rental::STATUS_CONFIRMED)
                ->where('start_date', '<', $now)
                ->update([
                    'status' => Rental::STATUS_LATE_PICKUP,
                    'updated_at' => $now,
                ]);

            // Update late returns
            $updatedReturns = DB::table('rentals')
                ->where('status', Rental::STATUS_ACTIVE)
                ->where('end_date', '<', $now)
                ->update([
                    'status' => Rental::STATUS_LATE_RETURN,
                    'updated_at' => $now,
                ]);

            DB::commit();

            // Log the updates
            if ($updatedExpired > 0 || $updatedPickups > 0 || $updatedReturns > 0) {
                Log::info("Late rentals check completed", [
                    'expired_updated' => $updatedExpired,
                    'late_pickups_updated' => $updatedPickups,
                    'late_returns_updated' => $updatedReturns,
                    'checked_at' => $now->toDateTimeString(),
                ]);
            }

            $this->newLine();
            $this->info("✅ Update completed!");
            $this->line("   - Quotations expired: {$updatedExpired}");
            $this->line("   - Late pickups updated: {$updatedPickups}");
            $this->line("   - Late returns updated: {$updatedReturns}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error("❌ Error updating rentals: " . $e->getMessage());
            Log::error("Failed to update late rentals", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
