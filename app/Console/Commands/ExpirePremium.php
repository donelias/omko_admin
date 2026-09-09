<?php

namespace App\Console\Commands;

use App\Models\Projects;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpirePremium extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-premium';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke is_premium from properties and projects whose premium_expiry_date has passed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        $expiredProperties = Property::where('is_premium', 1)
            ->whereNotNull('premium_expiry_date')
            ->where('premium_expiry_date', '<', $now)
            ->update(['is_premium' => 0]);

        if ($expiredProperties > 0) {
            $this->info("Revoked premium from {$expiredProperties} properties.");
        }

        $expiredProjects = Projects::where('is_premium', 1)
            ->whereNotNull('premium_expiry_date')
            ->where('premium_expiry_date', '<', $now)
            ->update(['is_premium' => 0]);

        if ($expiredProjects > 0) {
            $this->info("Revoked premium from {$expiredProjects} projects.");
        }

        if ($expiredProperties == 0 && $expiredProjects == 0) {
            $this->info('No premium listings expired.');
        }
    }
}