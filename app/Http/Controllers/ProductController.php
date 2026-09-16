<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        $data = $this->service->index();

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('products.index', $data);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->store($request->validated());

        return response()->json($product, 201);
    }
}
