<?php

namespace App\Contracts;

interface ProductRepositoryInterface
{
    /**
     * Return all stored products.
     */
    public function all(): array;

    /**
     * Save a product; update mode requires an existing ID.
     */
    public function save(array $product, bool $update = false): void;
}
