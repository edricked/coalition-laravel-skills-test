<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Display the product inventory as the application landing page.
Route::get('/', [ProductController::class, 'index']);

// Serve the product page or its JSON listing.
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
// Validate and save a new product.
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
