<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use App\Services\PlatformMailConfigurator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Evita problemas de longitud en MySQL cuando usemos indices
        Schema::defaultStringLength(191);

        app(PlatformMailConfigurator::class)->apply();
    }
}