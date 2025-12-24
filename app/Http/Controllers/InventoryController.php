<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AddInventoryRequest;
use App\Http\Requests\GetInventoryRequest;
use App\Http\Resources\InventoryCollection;
use App\Services\Interfaces\InventoryServiceInterface;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryServiceInterface $inventoryService
    ) {}

    public function storeInventory(AddInventoryRequest $request)
    {
        try {
            $validated = $request->validated();
            $this->inventoryService->store(
                $validated['product_id'],
                $validated['quantity'],
                $validated['cost_price']
            );

            return response()->json(['message' => 'Inventory added successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add inventory', 'details' => $e->getMessage()], 500);
        }
    }

    public function getInventory(GetInventoryRequest $request)
    {
        try{
            $validated = $request->validated();
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 10;

            $inventoryData = $this->inventoryService->getInventory($page, $perPage);

            return new InventoryCollection($inventoryData);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve inventory'], 500);
        }
    }

}
