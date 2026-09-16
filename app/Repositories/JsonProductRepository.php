<?php

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\File;
use OutOfBoundsException;
use RuntimeException;

class JsonProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly string $path) {}

    public function all(): array
    {
        return $this->withLock(LOCK_SH, function (): array {
            $products = $this->readProducts();

            usort($products, fn (array $first, array $second) => strcmp($first['submittedAt'], $second['submittedAt']));

            return $products;
        });
    }

    public function save(array $product, bool $update = false): void
    {
        $this->validateProduct($product);

        $this->withLock(LOCK_EX, function () use ($product, $update): void {
            $products = $this->readProducts();

            foreach ($products as $index => $existingProduct) {
                if ($existingProduct['id'] !== $product['id']) {
                    continue;
                }

                $product['submittedAt'] = $existingProduct['submittedAt'];
                $products[$index] = $product;
                $this->writeProducts($products);

                return;
            }

            if ($update) {
                throw new OutOfBoundsException('Product not found.');
            }

            $products[] = $product;
            $this->writeProducts($products);
        });
    }

    private function readProducts(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $contents = trim(File::get($this->path));

        if ($contents === '') {
            return [];
        }

        // Decode objects separately so an empty JSON object is not treated as a list.
        $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('Product storage must contain a JSON array.');
        }

        $products = [];

        foreach ($decoded as $record) {
            if (! $record instanceof \stdClass) {
                throw new RuntimeException('Product storage contains an invalid record.');
            }

            $product = (array) $record;
            $this->validateProduct($product);
            $products[] = $product;
        }

        return $products;
    }

    private function validateProduct(array $product): void
    {
        foreach (['id', 'name', 'submittedAt'] as $field) {
            if (! is_string($product[$field] ?? null) || trim($product[$field]) === '') {
                throw new RuntimeException("Product field {$field} must be a non-empty string.");
            }
        }

        foreach (['quantity', 'priceCents'] as $field) {
            if (! is_int($product[$field] ?? null) || $product[$field] < 0) {
                throw new RuntimeException("Product field {$field} must be a non-negative integer.");
            }
        }
    }

    private function writeProducts(array $products): void
    {
        $json = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $temporary = tempnam(dirname($this->path), 'products-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create temporary product storage.');
        }

        try {
            if (file_put_contents($temporary, $json) !== strlen($json)) {
                throw new RuntimeException('Unable to write product storage.');
            }

            if (! rename($temporary, $this->path)) {
                throw new RuntimeException('Unable to replace product storage.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function withLock(int $mode, callable $callback): mixed
    {
        File::ensureDirectoryExists(dirname($this->path), 0700);

        // The separate lock remains valid when the JSON file is replaced.
        $lock = fopen($this->path.'.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('Unable to open the product storage lock.');
        }

        try {
            if (! flock($lock, $mode)) {
                throw new RuntimeException('Unable to lock product storage.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
