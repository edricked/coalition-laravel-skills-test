<?php

namespace App\Repositories;

use App\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\File;
use OutOfBoundsException;
use RuntimeException;

class JsonProductRepository implements ProductRepositoryInterface
{
    /**
     * Set the path of the product JSON file.
     */
    public function __construct(private readonly string $path) {}

    /**
     * Read stored products in submission order, oldest first.
     */
    public function all(): array
    {
        return $this->withLock(LOCK_SH, function (): array {
            $products = $this->readProducts();

            usort($products, fn (array $first, array $second) => strcmp($first['submitted_at'], $second['submitted_at']));

            return $products;
        });
    }

    /**
     * Create or replace a product while preserving its submission time.
     */
    public function save(array $product, bool $update = false): void
    {
        $this->validateProduct($product);

        $this->withLock(LOCK_EX, function () use ($product, $update): void {
            $products = $this->readProducts();

            foreach ($products as $index => $existingProduct) {
                if ($existingProduct['id'] !== $product['id']) {
                    continue;
                }

                $product['submitted_at'] = $existingProduct['submitted_at'];
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

    /**
     * Read and validate JSON records, treating missing or empty files as empty lists.
     */
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

    /**
     * Check the required fields and types of a stored product.
     */
    private function validateProduct(array $product): void
    {
        foreach (['id', 'name', 'submitted_at'] as $field) {
            if (! is_string($product[$field] ?? null) || trim($product[$field]) === '') {
                throw new RuntimeException("Product field {$field} must be a non-empty string.");
            }
        }

        foreach (['quantity', 'price_cents'] as $field) {
            if (! is_int($product[$field] ?? null) || $product[$field] < 0) {
                throw new RuntimeException("Product field {$field} must be a non-negative integer.");
            }
        }
    }

    /**
     * Write valid JSON to a temporary file before replacing the current file.
     */
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

    /**
     * Run a storage operation under a file lock and release it afterward.
     */
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
