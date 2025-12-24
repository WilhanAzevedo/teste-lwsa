<?php

namespace App\Repositories;

use App\Models\SaleItem;
use App\Repositories\Interfaces\SaleItemRepositoryInterface;

class SaleItemRepository implements SaleItemRepositoryInterface
{
    public function storeMany(array $items): void
    {
        SaleItem::insert($items);
    }
}