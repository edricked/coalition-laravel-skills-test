<?php

namespace App\Providers;

use App\Contracts\ProductRepositoryInterface;
use App\Repositories\JsonProductRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ProductRepositoryInterface::class, function () {
            return new JsonProductRepository(storage_path('app/products.json'));
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
