# Product Inventory

A Laravel 10 product inventory application that stores submissions in JSON and updates the product table using AJAX.

## Requirements

- PHP 8.1 or later
- Composer

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Open `http://127.0.0.1:8000` in a browser.

Product records are stored in `storage/app/products.json`. The application creates and locks the storage file as needed.

## Tests

```bash
php artisan test
```
