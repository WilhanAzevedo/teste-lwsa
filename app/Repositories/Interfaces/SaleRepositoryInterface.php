<?php

namespace App\Repositories\Interfaces;

use App\Enums\SaleStatus;
use App\Models\Sale;

interface SaleRepositoryInterface
{
    public function create(array $data): Sale;
    public function updateStatus(int $saleId, SaleStatus $status): void;
    public function getById(int $id): Sale;
}