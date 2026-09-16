<?php

namespace Tests\Feature;

use App\Contracts\ProductRepositoryInterface;
use App\Repositories\JsonProductRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductControllerTest extends TestCase
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
            ->assertViewHas('grand_total_cents', 0);
    }

    public function test_store_generates_id_timestamp_and_total_and_returns_201(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 9, 16)->startOfDay());

        $response = $this->postJson('/products', [
            'name' => 'Notebook', 'quantity' => '3', 'price' => '12.50',
        ])->assertCreated()
            ->assertJsonPath('price_cents', 1250)
            ->assertJsonPath('total_cents', 3750)
            ->assertJsonPath('submitted_at', '2026-09-16T00:00:00.000000Z');

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
            ->assertJsonPath('grand_total_cents', 4000);
    }

    public function test_invalid_fields_return_422_and_are_not_saved(): void
    {
        $this->postJson('/products', ['name' => '', 'quantity' => -1, 'price' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'quantity', 'price']);

        $this->getJson('/products')->assertJsonPath('products', []);
    }
    /** Verify saved JSON can be read by a new repository instance. */
    public function test_created_product_is_persisted_as_valid_json(): void
    {
        $response = $this->postJson('/products', [
            'name' => 'Notebook "Blue"', 'quantity' => '2', 'price' => '0.10',
        ])->assertCreated()->assertJsonPath('total_cents', 20);

        $path = $this->directory.'/products.json';
        $records = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, $records);
        $this->assertSame($response->json('id'), $records[0]['id']);
        $this->assertSame('Notebook "Blue"', $records[0]['name']);
        $this->assertSame(10, $records[0]['price_cents']);
        $this->assertSame($records, (new JsonProductRepository($path))->all());
    }

    /** Check required fields and invalid values without changing saved records. */
    public function test_validation_rejects_missing_and_out_of_range_fields(): void
    {
        $this->postJson('/products', [
            'name' => 'Existing', 'quantity' => 1, 'price' => '1.00',
        ])->assertCreated();
        $path = $this->directory.'/products.json';
        $original = File::get($path);

        foreach ([
            [],
            ['name' => ' ', 'quantity' => '1.5', 'price' => '-1.00'],
            ['name' => str_repeat('a', 201), 'quantity' => 1000000, 'price' => '1000000.00'],
            ['name' => ['invalid'], 'quantity' => 'abc', 'price' => '1.001'],
        ] as $input) {
            $this->postJson('/products', $input)->assertUnprocessable()
                ->assertJsonValidationErrors(['name', 'quantity', 'price']);
            $this->assertSame($original, File::get($path));
        }
    }

    /** Zero stock and a zero price are valid inputs. */
    public function test_zero_values_are_accepted_and_names_are_trimmed(): void
    {
        $this->postJson('/products', [
            'name' => ' Free sample ', 'quantity' => 0, 'price' => '0',
        ])->assertCreated()->assertJsonPath('name', 'Free sample')
            ->assertJsonPath('quantity', 0)->assertJsonPath('total_cents', 0);
    }

    /** Updating storage keeps the original submission time and does not duplicate the record. */
    public function test_repository_edit_preserves_submission_time_and_updates_totals(): void
    {
        $created = $this->postJson('/products', [
            'name' => 'Notebook', 'quantity' => 3, 'price' => '12.50',
        ])->assertCreated()->json();

        $repository = $this->app->make(ProductRepositoryInterface::class);
        $product = $repository->all()[0];
        $product['quantity'] = 4;
        $product['submitted_at'] = '2099-01-01T00:00:00.000000Z';
        $repository->save($product, true);

        $this->getJson('/products')->assertOk()->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.submitted_at', $created['submitted_at'])
            ->assertJsonPath('products.0.total_cents', 5000)
            ->assertJsonPath('grand_total_cents', 5000);
    }

    /** Update mode must not silently create a missing product. */
    public function test_repository_rejects_editing_a_missing_product(): void
    {
        $repository = $this->app->make(ProductRepositoryInterface::class);

        try {
            $repository->save([
                'id' => (string) Str::uuid(), 'name' => 'Missing',
                'quantity' => 1, 'price_cents' => 100,
                'submitted_at' => '2026-09-16T00:00:00.000000Z',
            ], true);
            $this->fail('Updating a missing product should fail.');
        } catch (\OutOfBoundsException $exception) {
            $this->assertSame([], $repository->all());
        }
    }
}
