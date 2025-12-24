<?php

namespace App\Repositories\Interfaces;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryRepositoryInterface
{
    public function updateByProductId(int $productId, int $quantity): Inventory;

    public function getInventory(int $page, int $perPage) : LengthAwarePaginator;
}