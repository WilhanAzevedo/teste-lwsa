<?php
namespace App\Services;

use App\Models\Inventory;
use App\Repositories\Interfaces\InventoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\Interfaces\InventoryServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService implements InventoryServiceInterface
{
    private const CACHE_TAG = 'summary_inventory';

    public function __construct(
        protected InventoryRepositoryInterface $inventoryRepo,
        protected ProductRepositoryInterface $productRepo
    ) {}

    public function store(int $productId, int $quantity, float $costPrice): Inventory
    {
        
        return DB::transaction(function () use ($productId, $quantity, $costPrice) {
            try {
                $product = $this->productRepo->getById($productId);
                if (!$product) {
                    throw new \Exception("Product not found");
                }

                $inventory = $this->inventoryRepo->updateByProductId($productId, $quantity);

                $this->productRepo->updateById($productId, [
                    'cost_price' => $costPrice,
                ]);

                Cache::tags([self::CACHE_TAG])->flush();
                
                return $inventory->load('product');

            } catch (\Exception $e) {
                
                Log::error("Falha na operação de estoque ID {$productId}: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);

                throw new \Exception("Failed to store inventory");
            }
        });

    }

    public function getInventory(int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $cacheKey = "sumary_inventory_p{$page}_l{$perPage}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, 3600, function () use ($page, $perPage) {
            return $this->inventoryRepo->getInventory($page, $perPage);
        });
    }


}