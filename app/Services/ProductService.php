<?php

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Use the repository to read and save products.
     */
    public function __construct(private ProductRepositoryInterface $repository) {}

    /**
     * List products newest first with row totals and a grand total in cents.
     */
    public function index(): array
    {
        $products = $this->repository->all();

        usort($products, fn (array $first, array $second) => strcmp($second['submitted_at'], $first['submitted_at']));

        foreach ($products as &$product) {
            $product['total_cents'] = $product['quantity'] * $product['price_cents'];
        }
        unset($product);

        return [
            'products' => $products,
            'grand_total_cents' => array_sum(array_column($products, 'total_cents')),
        ];
    }

    /**
     * Convert the price to cents, generate an ID and timestamp, and save the product.
     */
    public function store(array $data): array
    {
        [$whole, $fraction] = array_pad(explode('.', $data['price'], 2), 2, '');
        $price_cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        $product = [
            'id' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'quantity' => (int) $data['quantity'],
            'price_cents' => $price_cents,
            'submitted_at' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
        ];

        $this->repository->save($product);
        $product['total_cents'] = $product['quantity'] * $price_cents;

        return $product;
    }
}
