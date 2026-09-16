<?php

namespace App\Services;

use App\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(private ProductRepositoryInterface $repository) {}

    public function index(): array
    {
        $products = $this->repository->all();

        usort($products, fn (array $first, array $second) => strcmp($second['submittedAt'], $first['submittedAt']));

        foreach ($products as &$product) {
            $product['totalCents'] = $product['quantity'] * $product['priceCents'];
        }
        unset($product);

        return [
            'products' => $products,
            'grandTotalCents' => array_sum(array_column($products, 'totalCents')),
        ];
    }

    public function store(array $data): array
    {
        [$whole, $fraction] = array_pad(explode('.', $data['price'], 2), 2, '');
        $priceCents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        $product = [
            'id' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'quantity' => (int) $data['quantity'],
            'priceCents' => $priceCents,
            'submittedAt' => now('UTC')->format('Y-m-d\TH:i:s.u\Z'),
        ];

        $this->repository->save($product);
        $product['totalCents'] = $product['quantity'] * $priceCents;

        return $product;
    }
}
