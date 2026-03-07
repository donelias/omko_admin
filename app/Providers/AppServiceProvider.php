<?php

namespace App\Providers;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        LogViewer::auth(function () {
            return auth()->check(); // Allow access only if the user is authenticated
        });
        $paths = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0775, true);
            }

            $this->fixPermissions($path);
        }
    }

    protected function fixPermissions($path)
    {
        if (is_dir($path)) {
            @chmod($path, 0775);
            foreach (scandir($path) as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->fixPermissions($path . DIRECTORY_SEPARATOR . $item);
            }
        } else {
            @chmod($path, 0664);
        }
    }
}
