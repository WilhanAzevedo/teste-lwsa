<?php

namespace App\Services\Interfaces;

use App\Models\Inventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InventoryServiceInterface
{
    public function store(int $productId, int $quantity, float $costPrice): Inventory;

    public function getInventory(int $page = 1, int $perPage = 10): LengthAwarePaginator;

    public function debit(int $productId, int $quantity): Inventory;
}