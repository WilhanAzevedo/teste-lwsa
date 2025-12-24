<?php

namespace Tests\Unit;

use App\Enums\SaleStatus;
use App\Events\SaleCreated;
use App\Listeners\UpdateInventory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Repositories\Interfaces\SaleRepositoryInterface;
use App\Services\Interfaces\InventoryServiceInterface;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateInventoryListenerTest extends TestCase
{
    protected UpdateInventory $listener;
    protected $inventoryService;
    protected $saleRepo;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock das dependências
        $this->inventoryService = Mockery::mock(InventoryServiceInterface::class);
        $this->saleRepo = Mockery::mock(SaleRepositoryInterface::class);

        // Instancia o listener com os mocks
        $this->listener = new UpdateInventory(
            $this->inventoryService,
            $this->saleRepo
        );

        // Fake do Log
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_should_process_inventory_debit_successfully()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;
        $sale->status = SaleStatus::PENDING;

        $item1 = new SaleItem([
            'id' => 1,
            'sale_id' => 1,
            'product_id' => 10,
            'quantity' => 2,
            'unit_price' => 150.00,
            'unit_cost' => 100.00,
        ]);

        $item2 = new SaleItem([
            'id' => 2,
            'sale_id' => 1,
            'product_id' => 20,
            'quantity' => 3,
            'unit_price' => 80.00,
            'unit_cost' => 50.00,
        ]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item1, $item2]));

        $event = new SaleCreated($sale);

        // Expectations
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(10, 2);

        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(20, 3);

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(1, SaleStatus::COMPLETED);

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertTrue(true); // Mock expectations verified
        Log::shouldHaveReceived('info')->with("Processing inventory debit for Sale #1")->once();
        Log::shouldHaveReceived('info')->with("Inventory successfully updated for Sale #1")->once();
    }

    #[Test]
    public function it_should_cancel_sale_when_inventory_debit_fails()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 1;

        $item = new SaleItem([
            'id' => 1,
            'sale_id' => 1,
            'product_id' => 10,
            'quantity' => 5,
            'unit_price' => 150.00,
            'unit_cost' => 100.00,
        ]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item]));

        $event = new SaleCreated($sale);

        // Simula falha no débito de estoque
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(10, 5)
            ->andThrow(new Exception('Insufficient stock. Available: 2, Requested: 5'));

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(1, SaleStatus::CANCELED);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to process sale: Insufficient or unavailable stock for product ID 10');

        $this->listener->handle($event);

        // Verify logs
        Log::shouldHaveReceived('info')->with("Processing inventory debit for Sale #1")->once();
        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function it_should_log_error_and_cancel_sale_on_first_item_failure()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 5;

        $item1 = new SaleItem([
            'product_id' => 100,
            'quantity' => 10,
        ]);

        $item2 = new SaleItem([
            'product_id' => 200,
            'quantity' => 5,
        ]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item1, $item2]));

        $event = new SaleCreated($sale);

        // Primeiro item falha
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(100, 10)
            ->andThrow(new Exception('Inventory not found'));

        // Segundo item não deve ser processado
        $this->inventoryService
            ->shouldReceive('debit')
            ->with(200, 5)
            ->never();

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(5, SaleStatus::CANCELED);

        // Assert & Act
        try {
            $this->listener->handle($event);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertEquals('Failed to process sale: Insufficient or unavailable stock for product ID 100', $e->getMessage());
        }

        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function it_should_process_multiple_items_successfully()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 10;

        $items = new Collection([
            new SaleItem(['product_id' => 1, 'quantity' => 2]),
            new SaleItem(['product_id' => 2, 'quantity' => 3]),
            new SaleItem(['product_id' => 3, 'quantity' => 1]),
        ]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn($items);

        $event = new SaleCreated($sale);

        // Expectations - todos os débitos devem ocorrer
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(1, 2);

        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(2, 3);

        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(3, 1);

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(10, SaleStatus::COMPLETED);

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertTrue(true); // Mock expectations verified
        Log::shouldHaveReceived('info')->twice();
    }

    #[Test]
    public function it_should_cancel_sale_on_second_item_failure()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 7;

        $item1 = new SaleItem(['product_id' => 10, 'quantity' => 2]);
        $item2 = new SaleItem(['product_id' => 20, 'quantity' => 5]);
        $item3 = new SaleItem(['product_id' => 30, 'quantity' => 1]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item1, $item2, $item3]));

        $event = new SaleCreated($sale);

        // Primeiro item sucesso
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(10, 2);

        // Segundo item falha
        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(20, 5)
            ->andThrow(new Exception('Insufficient stock'));

        // Terceiro item não deve ser processado
        $this->inventoryService
            ->shouldReceive('debit')
            ->with(30, 1)
            ->never();

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(7, SaleStatus::CANCELED);

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to process sale: Insufficient or unavailable stock for product ID 20');

        $this->listener->handle($event);
    }

    #[Test]
    public function it_should_update_sale_status_to_completed_after_all_debits()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 99;

        $item = new SaleItem(['product_id' => 1, 'quantity' => 1]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item]));

        $event = new SaleCreated($sale);

        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->with(1, 1);

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(99, SaleStatus::COMPLETED);

        // Act
        $this->listener->handle($event);

        // Assert - Verificação feita via shouldReceive
        $this->assertTrue(true);
    }

    #[Test]
    public function it_should_log_processing_start_and_success()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 50;

        $item = new SaleItem(['product_id' => 1, 'quantity' => 1]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item]));

        $event = new SaleCreated($sale);

        $this->inventoryService->shouldReceive('debit')->once();
        $this->saleRepo->shouldReceive('updateStatus')->once();

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertTrue(true); // Mock expectations verified
        Log::shouldHaveReceived('info')
            ->with("Processing inventory debit for Sale #50")
            ->once();

        Log::shouldHaveReceived('info')
            ->with("Inventory successfully updated for Sale #50")
            ->once();
    }

    #[Test]
    public function it_should_log_error_with_details_on_failure()
    {
        // Arrange
        /** @var Sale|\Mockery\MockInterface $sale */
        $sale = Mockery::mock(Sale::class)->makePartial();
        $sale->id = 42;

        $item = new SaleItem(['product_id' => 123, 'quantity' => 10]);

        $sale->shouldReceive('getAttribute')
            ->with('items')
            ->andReturn(new Collection([$item]));

        $event = new SaleCreated($sale);

        $this->inventoryService
            ->shouldReceive('debit')
            ->once()
            ->andThrow(new Exception('Stock error'));

        $this->saleRepo
            ->shouldReceive('updateStatus')
            ->once()
            ->with(42, SaleStatus::CANCELED);

        // Act
        try {
            $this->listener->handle($event);
        } catch (Exception $e) {
            // Expected
        }

        // Assert
        $this->assertTrue(true); // Mock expectations verified
        Log::shouldHaveReceived('error')
            ->with(Mockery::pattern('/Error debiting inventory for Sale #42, Product 123: Stock error/'))
            ->once();
    }
}
