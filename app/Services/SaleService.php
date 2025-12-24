<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Models\Sale;
use App\Repositories\Interfaces\InventoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\SaleItemRepositoryInterface;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use App\Services\Interfaces\SaleServiceInterface;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Cache;

class SaleService implements SaleServiceInterface
{
    public function __construct(
        protected SaleRepositoryInterface $saleRepo,
        protected SaleItemRepositoryInterface $saleItemRepo,
        protected ProductRepositoryInterface $productRepo,
        protected InventoryRepositoryInterface $inventoryRepo

    ) {}

    public function create(array $itemsData): Sale
    {
        return DB::transaction(function () use ($itemsData) {
            
            $totalAmount = 0;
            $totalCost = 0;
            $preparedItems = [];
            $now = now();

            $itemsData = collect($itemsData)->sortBy('product_id')->values()->all();

            foreach ($itemsData as $item) {
                $product = $this->productRepo->getById($item['product_id']);

                if (!$product) {
                    throw new Exception("Product ID {$item['product_id']} not found.");
                }

                $inventory = $this->inventoryRepo->findByProductIdLocked($product->id);

                $currentStock = $inventory?->quantity ?? 0;
                $requestedQty = $item['quantity'];

                if ($currentStock < $requestedQty) {
                    throw new Exception("Insufficient stock for product '{$product->name}'. Available: {$currentStock}, Requested: {$requestedQty}");
                }

                $totalAmount += $product->sale_price * $requestedQty;
                $totalCost += $product->cost_price * $requestedQty;

                $preparedItems[] = [
                    'product_id' => $product->id,
                    'quantity'   => $requestedQty,
                    'unit_price' => $product->sale_price,
                    'unit_cost'  => $product->cost_price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }


            $sale = $this->saleRepo->create([
                'total_amount' => $totalAmount,
                'total_cost'   => $totalCost,
                'total_profit' => $totalAmount - $totalCost,
                'status'       => SaleStatus::PENDING,
            ]);

            foreach ($preparedItems as &$item) {
                $item['sale_id'] = $sale->id;
            }

            $this->saleItemRepo->storeMany($preparedItems);

            SaleCreated::dispatch($sale);

            Cache::forget("sale_{$sale->id}");

            return $sale->load('items.product');
        });
    }

    public function getById(int $id): Sale
    {
        $cacheKey = "sale_{$id}";

        return Cache::remember($cacheKey, 3600, function () use ($id) {
            $sale = $this->saleRepo->getById($id);

            if (!$sale) {
                throw new Exception("Sale ID {$id} not found.");
            }

            return $sale->load('items.product');
        });
    }

}