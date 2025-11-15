<?php

namespace App\Providers;

use App\Listeners\LoginFailed;
use App\Listeners\LoginSuccessful;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

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
        \Schema::defaultStringLength(191);

        Event::listen(
            LoginSuccessful::class,
            LoginFailed::class
        );
    }
}
