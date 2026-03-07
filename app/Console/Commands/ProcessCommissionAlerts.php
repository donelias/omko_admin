<?php

namespace App\Console\Commands;

use App\Services\CommissionService;
use Illuminate\Console\Command;

class ProcessCommissionAlerts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'commission:process-alerts {--agent_id=} {--dry-run}';

    /**
     * The console description.
     */
    protected $description = 'Process and generate commission alerts for overdue payments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing commission alerts...');

        try {
            CommissionService::processOverdueAlerts();

            $this->info('✅ Commission alerts processed successfully!');
            $this->info('Check commission_alerts table for new alerts.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error processing alerts: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
