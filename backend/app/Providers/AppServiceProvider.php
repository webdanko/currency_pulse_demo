<?php

namespace App\Providers;

use App\Services\ExchangeRateProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExchangeRateProvider::class, function (): ExchangeRateProvider {
            $config = config('services.exchange');

            return new ExchangeRateProvider(
                baseUrl: $config['base_url'],
                base: $config['base'],
                symbols: $config['symbols'],
                cacheTtl: (int) $config['cache_ttl'],
                timeout: (int) $config['timeout'],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
