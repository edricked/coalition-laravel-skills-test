<?php

namespace Tests\Feature;

use App\Contracts\ProductRepositoryInterface;
use App\Repositories\JsonProductRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductEndpointsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/product-endpoints-'.Str::uuid();
        $this->app->instance(ProductRepositoryInterface::class,
            new JsonProductRepository($this->directory.'/products.json'));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_page_responds_with_an_empty_total(): void
    {
        $this->get('/products')->assertOk()->assertViewIs('products.index')
            ->assertViewHas('grandTotalCents', 0);
    }

    public function test_store_generates_id_timestamp_and_total_and_returns_201(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 16)->startOfDay());

        $response = $this->postJson('/products', [
            'name' => 'Notebook', 'quantity' => '3', 'price' => '12.50',
        ])->assertCreated()
            ->assertJsonPath('priceCents', 1250)
            ->assertJsonPath('totalCents', 3750)
            ->assertJsonPath('submittedAt', '2026-09-16T00:00:00.000000Z');

        $this->assertTrue(Str::isUuid($response->json('id')));
        $this->getJson('/products')->assertOk()
            ->assertJsonPath('products.0.id', $response->json('id'));
    }

    public function test_listing_returns_newest_first_and_calculates_grand_total(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 16)->startOfDay());
        $this->postJson('/products', ['name' => 'Notebook', 'quantity' => 3, 'price' => '12.50'])->assertCreated();
        $this->travel(1)->seconds();
        $this->postJson('/products', ['name' => 'Pen', 'quantity' => 2, 'price' => '1.25'])->assertCreated();

        $this->getJson('/products')->assertOk()
            ->assertJsonPath('products.0.name', 'Pen')
            ->assertJsonPath('products.1.name', 'Notebook')
            ->assertJsonPath('grandTotalCents', 4000);
    }

    public function test_invalid_fields_return_422_and_are_not_saved(): void
    {
        $this->postJson('/products', ['name' => '', 'quantity' => -1, 'price' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'quantity', 'price']);

        $this->getJson('/products')->assertJsonPath('products', []);
    }
}
