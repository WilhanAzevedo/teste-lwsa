<?php

namespace App\Repositories;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Repositories\Interfaces\SaleRepositoryInterface;

class SaleRepository implements SaleRepositoryInterface
{
    public function create(array $data): Sale
    {
        return Sale::create($data);
    }

    public function updateStatus(int $saleId, SaleStatus $status): void
    {
        Sale::where('id', $saleId)->update(['status' => $status]);
    }

    public function getById(int $id): Sale
    {
        return Sale::with('items.product')->findOrFail($id);
    }
}