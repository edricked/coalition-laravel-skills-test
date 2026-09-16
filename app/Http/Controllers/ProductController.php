<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Use the product service for listing and saving products.
     */
    public function __construct(private ProductService $service) {}

    /**
     * Return the product page or JSON for AJAX requests.
     */
    public function index(Request $request): View|JsonResponse
    {
        $data = $this->service->index();

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('products.index', $data);
    }

    /**
     * Save validated input and return the created product with HTTP 201.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->store($request->validated());

        return response()->json($product, 201);
    }
}
