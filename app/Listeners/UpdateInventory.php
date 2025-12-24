<?php

namespace App\Listeners;

use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Services\Interfaces\InventoryServiceInterface;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateInventory implements ShouldQueue
{
    public function __construct(
        protected InventoryServiceInterface $inventoryService,
        protected SaleRepositoryInterface $saleRepo
    ) {}

    /**
     * Manipula o evento.
     * @throws Exception Se o estoque falhar, a venda é cancelada (Rollback).
     */
    public function handle(SaleCreated $event): void
    {
        $sale = $event->sale;

        Log::info("Processing inventory debit for Sale #{$sale->id}");

        foreach ($sale->items as $item) {
            try {
                $this->inventoryService->debit(
                    $item->product_id,
                    $item->quantity
                );

            } catch (Exception $e) {
                Log::error("Error debiting inventory for Sale #{$sale->id}, Product {$item->product_id}: " . $e->getMessage());

                $this->saleRepo->updateStatus($sale->id, SaleStatus::CANCELED);
                throw new Exception("Failed to process sale: Insufficient or unavailable stock for product ID {$item->product_id}");
            }
        }

        $this->saleRepo->updateStatus($sale->id, SaleStatus::COMPLETED);
        Log::info("Inventory successfully updated for Sale #{$sale->id}");
    }
}