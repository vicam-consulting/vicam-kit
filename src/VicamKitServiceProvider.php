<?php

namespace Vicam\VicamKit;

use Illuminate\Support\ServiceProvider;
use Vicam\VicamKit\Commands\InstallCommand;

class VicamKitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
