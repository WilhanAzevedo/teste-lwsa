<?php

namespace Tests\Unit;

use App\Models\Inventory;
use App\Models\Product;
use App\Repositories\Interfaces\InventoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Services\InventoryService;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    protected InventoryService $inventoryService;
    protected $inventoryRepo;
    protected $productRepo;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dos repositories
        $this->inventoryRepo = Mockery::mock(InventoryRepositoryInterface::class);
        $this->productRepo = Mockery::mock(ProductRepositoryInterface::class);

        // Instancia o service com os mocks
        $this->inventoryService = new InventoryService(
            $this->inventoryRepo,
            $this->productRepo
        );

        // Fake do Cache
        Cache::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_should_store_inventory_successfully()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $product = new Product([
            'id' => 1,
            'sku' => 'PROD-001',
            'name' => 'Product Test',
            'cost_price' => 100.00,
            'sale_price' => 150.00,
        ]);
        $product->id = 1;

        $inventory = Mockery::mock(Inventory::class)->makePartial();
        $inventory->id = 1;
        $inventory->product_id = 1;
        $inventory->quantity = 50;

        // Expectations
        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->inventoryRepo
            ->shouldReceive('updateByProductId')
            ->once()
            ->with(1, 50)
            ->andReturn($inventory);

        $this->productRepo
            ->shouldReceive('updateById')
            ->once()
            ->with(1, ['cost_price' => 100.00]);

        $inventory->shouldReceive('load')
            ->once()
            ->with('product')
            ->andReturnSelf();

        // Act
        $result = $this->inventoryService->store(1, 50, 100.00);

        // Assert
        $this->assertInstanceOf(Inventory::class, $result);
        $this->assertEquals(1, $result->product_id);
        $this->assertEquals(50, $result->quantity);
        Cache::shouldHaveReceived('tags')->with(['summary_inventory'])->once();
    }

    #[Test]
    public function it_should_throw_exception_when_product_not_found_on_store()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(999)
            ->andReturn(null);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to store inventory');

        $this->inventoryService->store(999, 50, 100.00);
    }

    #[Test]
    public function it_should_get_inventory_with_pagination()
    {
        // Arrange
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['summary_inventory'])
            ->andReturnSelf();

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $key === 'sumary_inventory_p1_l10' && $ttl === 3600;
            })
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->inventoryRepo
            ->shouldReceive('getInventory')
            ->once()
            ->with(1, 10)
            ->andReturn($paginator);

        // Act
        $result = $this->inventoryService->getInventory(1, 10);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_should_get_inventory_with_custom_pagination()
    {
        // Arrange
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        Cache::shouldReceive('tags')
            ->once()
            ->with(['summary_inventory'])
            ->andReturnSelf();

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(function ($key, $ttl, $callback) {
                return $key === 'sumary_inventory_p2_l20' && $ttl === 3600;
            })
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $this->inventoryRepo
            ->shouldReceive('getInventory')
            ->once()
            ->with(2, 20)
            ->andReturn($paginator);

        // Act
        $result = $this->inventoryService->getInventory(2, 20);

        // Assert
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_should_debit_inventory_successfully()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        /** @var Inventory|\Mockery\MockInterface $inventory */
        $inventory = Mockery::mock(Inventory::class)->makePartial();
        $inventory->id = 1;
        $inventory->product_id = 1;
        $inventory->quantity = 50;

        // Expectations
        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(1)
            ->andReturn($inventory);

        $this->inventoryRepo
            ->shouldReceive('decrementStock')
            ->once()
            ->with(1, 10);

        $inventory->shouldReceive('refresh')
            ->once()
            ->andReturnSelf();

        // Act
        $result = $this->inventoryService->debit(1, 10);

        // Assert
        $this->assertInstanceOf(Inventory::class, $result);
        Cache::shouldHaveReceived('tags')->with(['summary_inventory'])->once();
    }

    #[Test]
    public function it_should_throw_exception_when_inventory_not_found_on_debit()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(999)
            ->andReturn(null);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Inventory not found for product ID 999');

        $this->inventoryService->debit(999, 10);
    }

    #[Test]
    public function it_should_throw_exception_when_insufficient_stock_on_debit()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        /** @var Inventory|\Mockery\MockInterface $inventory */
        $inventory = Mockery::mock(Inventory::class)->makePartial();
        $inventory->id = 1;
        $inventory->product_id = 1;
        $inventory->quantity = 5; // Estoque baixo

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(1)
            ->andReturn($inventory);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient stock. Available: 5, Requested: 10');

        $this->inventoryService->debit(1, 10);
    }

    #[Test]
    public function it_should_flush_cache_after_store()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $product = new Product(['id' => 1, 'name' => 'Product']);
        $product->id = 1;

        /** @var Inventory|\Mockery\MockInterface $inventory */
        $inventory = Mockery::mock(Inventory::class)->makePartial();
        $inventory->id = 1;

        $this->productRepo->shouldReceive('getById')->once()->andReturn($product);
        $this->inventoryRepo->shouldReceive('updateByProductId')->once()->andReturn($inventory);
        $this->productRepo->shouldReceive('updateById')->once();
        $inventory->shouldReceive('load')->once()->andReturnSelf();

        Cache::shouldReceive('tags')
            ->once()
            ->with(['summary_inventory'])
            ->andReturnSelf();

        Cache::shouldReceive('flush')
            ->once();

        // Act
        $result = $this->inventoryService->store(1, 50, 100.00);

        // Assert
        $this->assertInstanceOf(Inventory::class, $result);
        $this->assertTrue(true); // Assertions via Mockery expectations
    }

    #[Test]
    public function it_should_flush_cache_after_debit()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        /** @var Inventory|\Mockery\MockInterface $inventory */
        /** @var Inventory|\Mockery\MockInterface $inventory */
        $inventory = Mockery::mock(Inventory::class)->makePartial();
        $inventory->quantity = 50;

        $this->inventoryRepo->shouldReceive('findByProductIdLocked')->once()->andReturn($inventory);
        $this->inventoryRepo->shouldReceive('decrementStock')->once();
        $inventory->shouldReceive('refresh')->once()->andReturnSelf();

        Cache::shouldReceive('tags')
            ->once()
            ->with(['summary_inventory'])
            ->andReturnSelf();

        Cache::shouldReceive('flush')
            ->once();

        // Act
        $result = $this->inventoryService->debit(1, 10);

        // Assert
        $this->assertInstanceOf(Inventory::class, $result);
        $this->assertTrue(true); // Assertions via Mockery expectations
    }

    #[Test]
    public function it_should_update_product_cost_price_on_store()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $product = new Product(['id' => 1, 'cost_price' => 80.00]);
        $product->id = 1;

        /** @var Inventory|\Mockery\MockInterface $inventory */
        $inventory = Mockery::mock(Inventory::class)->makePartial();

        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->andReturn($product);

        $this->inventoryRepo
            ->shouldReceive('updateByProductId')
            ->once()
            ->andReturn($inventory);

        $this->productRepo
            ->shouldReceive('updateById')
            ->once()
            ->with(1, ['cost_price' => 120.00]); // Novo custo

        $inventory->shouldReceive('load')->once()->andReturnSelf();

        // Act
        $result = $this->inventoryService->store(1, 100, 120.00);

        // Assert
        $this->assertInstanceOf(Inventory::class, $result);
        $this->assertTrue(true); // Assertions via Mockery expectations
    }
}
