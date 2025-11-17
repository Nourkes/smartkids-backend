<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
    // Dans app/Providers/AppServiceProvider.php

public function register()
{
    $this->app->singleton(\App\Services\NotificationService::class);
}
}
