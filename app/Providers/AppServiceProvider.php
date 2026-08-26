<?php

namespace App\Providers;

use App\Services\TelegramAuth;
use App\Services\TelegramNotifier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramAuth::class, fn () => new TelegramAuth(
            (string) config('services.telegram.bot_token'),
        ));

        $this->app->singleton(TelegramNotifier::class, fn () => new TelegramNotifier(
            (string) config('services.telegram.bot_token'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
