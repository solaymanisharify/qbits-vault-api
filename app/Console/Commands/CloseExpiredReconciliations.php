<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CloseExpiredReconciliations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reconcile:close-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically expire pending reconciliations that did not start within 6 hours of their scheduled time.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired reconciliation sessions...');

        // Define the expiration boundary (6 hours ago from right now)
        $expirationThreshold = Carbon::now();

        /**
         * We search for records where:
         * 1. status is 'pending'
         * 2. it hasn't been locked yet (is_locked = false)
         * 3. The combined timestamp of (DATE(from_date) + audit_time) is older than 6 hours ago
         */
        $affectedRows = DB::table('reconciliations')
            ->where('status', 'pending')
            ->where('is_locked', false)
            ->whereRaw("
                CONCAT(DATE(from_date), ' ', audit_time) <= ?
            ", [$expirationThreshold->subHours(6)->toDateTimeString()])
            ->update([
                'status' => 'expired',
                'updated_at' => Carbon::now()
            ]);

        if ($affectedRows > 0) {
            $this->info("Successfully expired {$affectedRows} lingering reconciliation session(s).");
        } else {
            $this->comment('No expired reconciliation sessions found.');
        }

        return Command::SUCCESS;
    }
}
