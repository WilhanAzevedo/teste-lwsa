<?php

namespace App\Providers;

use App\Services\Interfaces\InventoryServiceInterface;
use App\Services\Interfaces\ProductServiceInterface;
use App\Services\Interfaces\ReportServiceInterface;
use App\Services\Interfaces\SaleItemsServiceInterface;
use App\Services\Interfaces\SaleServiceInterface;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\ReportService;
use App\Services\SaleItemsService;
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
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            InventoryServiceInterface::class,
        ];
    }
}