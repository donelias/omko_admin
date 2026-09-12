<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $isLocal = $this->app->environment('local');

        // In non-local environments Telescope must not collect/record request,
        // exception or job data. Production data is sensitive and should never
        // be persisted by a development tooling package.
        if (! $isLocal) {
            Telescope::stopRecording();
        }

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }
    // public function register()
    // {
    //     Telescope::filter(function (IncomingEntry $entry) {
    //         return $entry->type === 'request';
    //     });
    // }

    protected function authorization()
    {

        Telescope::auth(function () {
            // Only administrators may access Telescope. Regular authenticated
            // users (customers/agents) must never be able to view logged
            // requests, exceptions or payloads.
            $user = auth()->user();
            return $user && intval($user->type) === 0;
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        // if ($this->app->environment('local')) {
        //     return;
        // }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
