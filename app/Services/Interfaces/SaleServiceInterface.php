<?php

namespace App\Services\Interfaces;

use App\Models\Sale;

interface SaleServiceInterface
{
    public function create(array $itemsData): Sale;

    public function getById(int $id): Sale;
}