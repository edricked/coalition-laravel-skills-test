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

// Display the default welcome page.
Route::get('/', function () {
    return view('welcome');
});

// Serve the product page or its JSON listing.
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
// Validate and save a new product.
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
