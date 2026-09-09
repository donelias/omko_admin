<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (env('DEMO_MODE') == true) {
            $schedule->command('demo:remove-advertisements')->daily();
            $schedule->command('demo:remove-customers')->daily();
            $schedule->command('demo:remove-chats')->daily();
            $schedule->command('demo:remove-properties')->daily();
            $schedule->command('demo:remove-projects')->daily();
        }
        $schedule->command('app:notify-expiring-subscriptions')->daily();

        // Daily automated database backup (mysqldump)
        $schedule->command('backup:database')->dailyAt('02:00')->withoutOverlapping();

        // Fresh daily exchange rate (Google Finance USD/DOP) for the price engine
        $schedule->command('price:fetch-exchange-rates')->dailyAt('08:00')->withoutOverlapping();
        // Generate AI price suggestions for active properties (after exchange rates)
        $schedule->command('price:generate-suggestions --limit=100')->dailyAt('09:00')->withoutOverlapping();
        // Auto-cancel pending appointments based on preferences
        $schedule->command('appointments:auto-cancel')->everyFiveMinutes();

        // Retry failed jobs
        // $schedule->command('queue:retry all')->everyMinute();
        // Work on the queue
        $schedule->command('queue:work --stop-when-empty --timeout=300 --memory=512 --tries=3 --max-jobs=50')->everyMinute();

        // Clear Listing Counts for last 3 months
        $schedule->command('app:clear-listing-counts')->dailyAt('00:00');

        // Make pending transactions failed
        $schedule->command('app:make-pending-transactions-failed')->everyMinute();

        $schedule->command('app:expire-listings')->hourly();
        $schedule->command('app:expire-premium')->hourly();

        // Hard-delete stories past their 24-hour window and remove their files
        $schedule->command('stories:delete-expired')->hourly();
    }

    /**p
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        $this->load(__DIR__.'/Commands/Demo');

        require base_path('routes/console.php');
    }
}
