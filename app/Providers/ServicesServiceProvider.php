<?php

namespace App\Providers;

use App\Services\Interfaces\InventoryServiceInterface;
use App\Services\Interfaces\SaleServiceInterface;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Support\ServiceProvider;

class ServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(InventoryServiceInterface::class, InventoryService::class);
        $this->app->bind(SaleServiceInterface::class, SaleService::class);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            InventoryServiceInterface::class,
            SaleServiceInterface::class,
        ];
    }
}