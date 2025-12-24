<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Repositories\Interfaces\InventoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function updateByProductId(int $productId, int $quantity): Inventory
    {
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $productId],
            ['quantity' => 0]
        );

        $inventory->increment('quantity', $quantity);
        $inventory->touch('last_updated');

        return $inventory;
    }


    public function getInventory(int $page, int $perPage): LengthAwarePaginator
    {
        return Inventory::query()
            ->join('products as p', 'p.id', '=', 'inventory.product_id')
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                DB::raw('SUM(inventory.quantity) as total_quantity'),
                DB::raw('SUM(inventory.quantity * p.cost_price) as total_cost'),
                DB::raw('SUM(inventory.quantity * p.sale_price) as total_sale'),
                DB::raw('(SUM(inventory.quantity * p.sale_price) - SUM(inventory.quantity * p.cost_price)) as projected_profit'),
            ])
            ->groupBy('p.id')
            ->orderBy('p.name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findByProductId(int $productId): ?Inventory
    {
        // Retorna o model ou null se não existir estoque ainda
        return Inventory::where('product_id', $productId)->first();
    }

    public function decrementStock(int $productId, int $quantity): Inventory
    {
        // Busca, decrementa e atualiza data
        $inventory = Inventory::where('product_id', $productId)->firstOrFail();
        $inventory->decrement('quantity', $quantity);
        $inventory->touch('last_updated');

        return $inventory;
    }

    public function findByProductIdLocked(int $productId): ?Inventory
    {
        // O Repository é o único lugar autorizado a falar "Eloquentsês"
        return Inventory::where('product_id', $productId)
            ->lockForUpdate()
            ->first();
    }
}
