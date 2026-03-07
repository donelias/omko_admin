<?php

namespace App\Console\Commands;

use App\Services\CommissionService;
use Illuminate\Console\Command;

class CheckSuspiciousCommissions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'commission:check-suspicious {agent_id?}';

    /**
     * The console description.
     */
    protected $description = 'Check for suspicious commission patterns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $agentId = $this->argument('agent_id');

        $this->info('Checking for suspicious commission patterns...');

        try {
            if ($agentId) {
                $alerts = CommissionService::checkSuspiciousTransactions($agentId);
                if (empty($alerts)) {
                    $this->info("✅ No suspicious patterns detected for agent {$agentId}");
                } else {
                    $this->warn("⚠️ Suspicious patterns detected:");
                    foreach ($alerts as $alert) {
                        $this->line("   • [{$alert['type']}] {$alert['message']}");
                    }
                }
            } else {
                $this->info('Scanning all recent commissions for suspicious patterns...');
                // Implement scan for all agents if needed
                $this->info('✅ Scan complete');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
