<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Services\Interfaces\SaleServiceInterface;
use App\Http\Resources\SaleResource;
use Exception;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(
        protected SaleServiceInterface $saleService
    ) {}

    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->saleService->create($request->validated()['items']);

            return response()->json(['message' => 'Sale created successfully'], 201);

        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create sale', 'details' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $sale = $this->saleService->getById($id);

            return response()->json(new SaleResource($sale), 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to retrieve sale', 'details' => $e->getMessage()], 422);
        }
    }
}