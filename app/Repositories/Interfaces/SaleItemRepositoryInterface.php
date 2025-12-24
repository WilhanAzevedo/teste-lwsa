<?php

namespace App\Repositories\Interfaces;

interface SaleItemRepositoryInterface
{
    public function storeMany(array $items): void;
}