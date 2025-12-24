<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;

class ProductRepository implements ProductRepositoryInterface
{
    public function getById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function updateById(int $id, array $data): bool
    {
        $product = Product::find($id);
        if (!$product) {
            return false;
        }

        return $product->update($data);
    }
}