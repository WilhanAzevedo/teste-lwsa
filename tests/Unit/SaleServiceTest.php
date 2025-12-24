<?php

namespace Tests\Unit;

use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Repositories\Interfaces\InventoryRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\Interfaces\SaleItemRepositoryInterface;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use App\Services\SaleService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    protected SaleService $saleService;
    protected $saleRepo;
    protected $saleItemRepo;
    protected $productRepo;
    protected $inventoryRepo;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dos repositories
        $this->saleRepo = Mockery::mock(SaleRepositoryInterface::class);
        $this->saleItemRepo = Mockery::mock(SaleItemRepositoryInterface::class);
        $this->productRepo = Mockery::mock(ProductRepositoryInterface::class);
        $this->inventoryRepo = Mockery::mock(InventoryRepositoryInterface::class);

        // Instancia o service com os mocks
        $this->saleService = new SaleService(
            $this->saleRepo,
            $this->saleItemRepo,
            $this->productRepo,
            $this->inventoryRepo
        );

        // Fake de eventos
        Event::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_should_create_a_sale_successfully()
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

        $inventory = new Inventory([
            'product_id' => 1,
            'quantity' => 10,
        ]);

        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;
        $sale->total_amount = 300.00;
        $sale->total_cost = 200.00;
        $sale->total_profit = 100.00;
        $sale->status = SaleStatus::PENDING;

        $itemsData = [
            ['product_id' => 1, 'quantity' => 2],
        ];

        // Expectations
        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(1)
            ->andReturn($inventory);

        $this->saleRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['total_amount'] == 300.00
                    && $data['total_cost'] == 200.00
                    && $data['total_profit'] == 100.00
                    && $data['status'] === SaleStatus::PENDING;
            })
            ->andReturn($sale);

        $this->saleItemRepo
            ->shouldReceive('storeMany')
            ->once()
            ->withArgs(function ($items) {
                return count($items) === 1
                    && $items[0]['product_id'] === 1
                    && $items[0]['quantity'] === 2
                    && $items[0]['unit_price'] == 150.00
                    && $items[0]['unit_cost'] == 100.00
                    && $items[0]['sale_id'] === 1;
            });

        $sale->shouldReceive('load')
            ->once()
            ->with('items.product')
            ->andReturnSelf();

        // Act
        $result = $this->saleService->create($itemsData);

        // Assert
        $this->assertInstanceOf(Sale::class, $result);
        $this->assertEquals(1, $result->id);
        Event::assertDispatched(SaleCreated::class, function ($event) use ($sale) {
            return $event->sale->id === $sale->id;
        });
    }

    #[Test]
    public function it_should_throw_exception_when_product_not_found()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $itemsData = [
            ['product_id' => 999, 'quantity' => 2],
        ];

        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(999)
            ->andReturn(null);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Product ID 999 not found.');

        $this->saleService->create($itemsData);
    }

    #[Test]
    public function it_should_throw_exception_when_insufficient_stock()
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

        $inventory = new Inventory([
            'product_id' => 1,
            'quantity' => 1, // Estoque insuficiente
        ]);

        $itemsData = [
            ['product_id' => 1, 'quantity' => 5], // Solicita mais do que tem
        ];

        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(1)
            ->andReturn($inventory);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient stock for product 'Product Test'. Available: 1, Requested: 5");

        $this->saleService->create($itemsData);
    }

    #[Test]
    public function it_should_throw_exception_when_no_inventory_exists()
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

        $itemsData = [
            ['product_id' => 1, 'quantity' => 2],
        ];

        $this->productRepo
            ->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($product);

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->once()
            ->with(1)
            ->andReturn(null); // Sem inventory

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Insufficient stock for product 'Product Test'. Available: 0, Requested: 2");

        $this->saleService->create($itemsData);
    }

    #[Test]
    public function it_should_calculate_totals_correctly_with_multiple_items()
    {
        // Arrange
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $product1 = new Product([
            'id' => 1,
            'sku' => 'PROD-001',
            'name' => 'Product 1',
            'cost_price' => 100.00,
            'sale_price' => 150.00,
        ]);
        $product1->id = 1;

        $product2 = new Product([
            'id' => 2,
            'sku' => 'PROD-002',
            'name' => 'Product 2',
            'cost_price' => 50.00,
            'sale_price' => 80.00,
        ]);
        $product2->id = 2;

        $inventory1 = new Inventory(['product_id' => 1, 'quantity' => 10]);
        $inventory2 = new Inventory(['product_id' => 2, 'quantity' => 20]);

        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;
        $sale->total_amount = 460.00; // (150*2) + (80*2)
        $sale->total_cost = 300.00;   // (100*2) + (50*2)
        $sale->total_profit = 160.00;
        $sale->status = SaleStatus::PENDING;

        $itemsData = [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 2],
        ];

        // Expectations
        $this->productRepo
            ->shouldReceive('getById')
            ->with(1)
            ->once()
            ->andReturn($product1);

        $this->productRepo
            ->shouldReceive('getById')
            ->with(2)
            ->once()
            ->andReturn($product2);

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->with(1)
            ->once()
            ->andReturn($inventory1);

        $this->inventoryRepo
            ->shouldReceive('findByProductIdLocked')
            ->with(2)
            ->once()
            ->andReturn($inventory2);

        $this->saleRepo
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['total_amount'] == 460.00
                    && $data['total_cost'] == 300.00
                    && $data['total_profit'] == 160.00;
            })
            ->andReturn($sale);

        $this->saleItemRepo
            ->shouldReceive('storeMany')
            ->once()
            ->withArgs(function ($items) {
                return count($items) === 2;
            });

        $sale->shouldReceive('load')
            ->once()
            ->with('items.product')
            ->andReturnSelf();

        // Act
        $result = $this->saleService->create($itemsData);

        // Assert
        $this->assertInstanceOf(Sale::class, $result);
    }

    #[Test]
    public function it_should_get_sale_by_id_successfully()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;
        $sale->total_amount = 300.00;
        $sale->total_cost = 200.00;
        $sale->total_profit = 100.00;
        $sale->status = SaleStatus::COMPLETED;

        $this->saleRepo
            ->shouldReceive('getById')
            ->once()
            ->with(1)
            ->andReturn($sale);

        $sale->shouldReceive('load')
            ->once()
            ->with('items.product')
            ->andReturnSelf();

        // Act
        $result = $this->saleService->getById(1);

        // Assert
        $this->assertInstanceOf(Sale::class, $result);
        $this->assertEquals(1, $result->id);
    }

    #[Test]
    public function it_should_throw_exception_when_sale_not_found()
    {
        // Arrange
        $this->saleRepo
            ->shouldReceive('getById')
            ->once()
            ->with(999)
            ->andThrow(new Exception('Sale ID 999 not found.'));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Sale ID 999 not found.');

        $this->saleService->getById(999);
    }

    #[Test]
    public function it_should_dispatch_sale_created_event()
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

        $inventory = new Inventory(['product_id' => 1, 'quantity' => 10]);

        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;
        $sale->total_amount = 150.00;
        $sale->total_cost = 100.00;
        $sale->total_profit = 50.00;
        $sale->status = SaleStatus::PENDING;

        $itemsData = [['product_id' => 1, 'quantity' => 1]];

        $this->productRepo->shouldReceive('getById')->once()->andReturn($product);
        $this->inventoryRepo->shouldReceive('findByProductIdLocked')->once()->andReturn($inventory);
        $this->saleRepo->shouldReceive('create')->once()->andReturn($sale);
        $this->saleItemRepo->shouldReceive('storeMany')->once();
        $sale->shouldReceive('load')->once()->with('items.product')->andReturnSelf();

        // Act
        $this->saleService->create($itemsData);

        // Assert
        Event::assertDispatched(SaleCreated::class);
    }
}
