<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;


interface ProductRepositoryInterface
{
    public function getById(int $id): ?Product;

    public function updateById(int $id, array $data): bool;
}