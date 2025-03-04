<?php

namespace VoyagerToFilament;

use Illuminate\Support\ServiceProvider;
use VoyagerToFilament\Commands\ExportVoyagerToFilament;

class VoyagerToFilamentServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/voyager-to-filament.php', 'voyager-to-filament');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/voyager-to-filament.php' => config_path('voyager-to-filament.php'),
            ], 'config');

            $this->commands([
                ExportVoyagerToFilament::class,
                ImportVoyagerToFilament::class,
            ]);
        }
    }
}
