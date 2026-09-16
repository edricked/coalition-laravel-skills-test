<?php

namespace Tests\Unit;

use App\Contracts\ProductRepositoryInterface;
use App\Services\ProductService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    /** Convert decimal strings to exact cents before calculating each row total. */
    #[DataProvider('priceCases')]
    public function test_creation_converts_money_and_generates_product_metadata(
        string $price,
        int $quantity,
        int $expectedPrice,
        int $expectedTotal,
    ): void {
        $this->travelTo(now('UTC')->setDate(2026, 9, 16)->startOfDay());

        try {
            $saved = null;
            $repository = $this->createMock(ProductRepositoryInterface::class);
            $repository->expects($this->once())->method('save')
                ->willReturnCallback(function (array $product, bool $update = false) use (&$saved): void {
                    $this->assertFalse($update);
                    $saved = $product;
                });

            $product = (new ProductService($repository))->store([
                'name' => ' Notebook ', 'quantity' => (string) $quantity, 'price' => $price,
            ]);

            $this->assertTrue(Str::isUuid($product['id']));
            $this->assertSame('2026-09-16T00:00:00.000000Z', $product['submitted_at']);
            $this->assertSame('Notebook', $product['name']);
            $this->assertSame($quantity, $product['quantity']);
            $this->assertSame($expectedPrice, $product['price_cents']);
            $this->assertSame($expectedTotal, $product['total_cents']);
            $this->assertSame($product['id'], $saved['id']);
            $this->assertSame($expectedPrice, $saved['price_cents']);
        } finally {
            $this->travelBack();
        }
    }

    /** Include values that commonly reveal floating-point or decimal-padding mistakes. */
    public static function priceCases(): array
    {
        return [
            'whole price' => ['12', 3, 1200, 3600],
            'one decimal' => ['1.2', 3, 120, 360],
            'two decimals' => ['12.50', 3, 1250, 3750],
            'small amount' => ['0.10', 3, 10, 30],
            'zero price' => ['0', 3, 0, 0],
            'zero stock' => ['12.50', 0, 1250, 0],
            'maximum input' => ['999999.99', 999999, 99999999, 99999899000001],
        ];
    }

    /** Return a zero grand total when no products have been stored. */
    public function test_empty_listing_has_zero_grand_total(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('all')->willReturn([]);

        $this->assertSame(
            ['products' => [], 'grand_total_cents' => 0],
            (new ProductService($repository))->index(),
        );
    }

    /** Sort unsorted records newest first and calculate totals from current values. */
    public function test_listing_sorts_records_and_recalculates_totals(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->method('all')->willReturn([
            ['id' => 'old', 'quantity' => 3, 'price_cents' => 1250, 'total_cents' => 999, 'submitted_at' => '2026-09-15T00:00:00.000000Z'],
            ['id' => 'new', 'quantity' => 2, 'price_cents' => 125, 'submitted_at' => '2026-09-16T00:00:00.000000Z'],
            ['id' => 'middle', 'quantity' => 0, 'price_cents' => 500, 'submitted_at' => '2026-09-15T12:00:00.000000Z'],
        ]);

        $result = (new ProductService($repository))->index();

        $this->assertSame(['new', 'middle', 'old'], array_column($result['products'], 'id'));
        $this->assertSame([250, 0, 3750], array_column($result['products'], 'total_cents'));
        $this->assertSame(4000, $result['grand_total_cents']);
    }

    /** Each creation receives a different product ID. */
    public function test_creating_two_products_generates_distinct_ids(): void
    {
        $repository = $this->createMock(ProductRepositoryInterface::class);
        $repository->expects($this->exactly(2))->method('save');
        $service = new ProductService($repository);
        $input = ['name' => 'Pen', 'quantity' => 1, 'price' => '1.00'];

        $first = $service->store($input);
        $second = $service->store($input);

        $this->assertNotSame($first['id'], $second['id']);
    }
}
