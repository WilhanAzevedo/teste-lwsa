<?php

namespace App\Providers;

use App\Repositories\Interfaces\InventoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\SaleItemRepositoryInterface;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleItemRepository;
use App\Repositories\SaleRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
        $this->app->bind(SaleRepositoryInterface::class, SaleRepository::class);
        $this->app->bind(SaleItemRepositoryInterface::class, SaleItemRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function provides(): array
    {
        return [
            ProductRepositoryInterface::class,
            InventoryRepositoryInterface::class,
            SaleRepositoryInterface::class,
            SaleItemRepositoryInterface::class,
        ];
    }
}