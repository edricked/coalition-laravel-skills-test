<?php

namespace App\Contracts;

interface ProductRepositoryInterface
{
    public function all(): array;

    public function save(array $product, bool $update = false): void;
}
