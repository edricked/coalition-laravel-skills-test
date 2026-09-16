<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Product inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/products.css') }}" rel="stylesheet">
    <script src="{{ asset('js/products.js') }}" defer></script>
</head>
<body class="bg-light">
    <main class="container py-5">
        <h1 class="mb-4">Product inventory</h1>

        <section class="card shadow-sm mb-4" aria-labelledby="form-title">
            <div class="card-body">
                <h2 class="h5 mb-3" id="form-title">Add product</h2>
                {{-- Submit product details through AJAX and show errors beside each field. --}}
                <form id="product-form" action="{{ route('products.store') }}" method="post" data-list-url="{{ route('products.index') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Product name</label>
                            <input class="form-control" id="name" name="name" type="text" maxlength="200" required aria-describedby="name-error">
                            <div class="invalid-feedback" id="name-error"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="quantity">Quantity in stock</label>
                            <input class="form-control" id="quantity" name="quantity" type="text" inputmode="numeric" pattern="[0-9]{1,6}" required aria-describedby="quantity-error">
                            <div class="invalid-feedback" id="quantity-error"></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="price">Price per item</label>
                            <input class="form-control" id="price" name="price" type="text" inputmode="decimal" pattern="(0|[1-9][0-9]{0,5})(\.[0-9]{1,2})?" required aria-describedby="price-error">
                            <div class="invalid-feedback" id="price-error"></div>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Save product</button>
                </form>
                <noscript><p class="mt-3">Enable JavaScript to save products and update this table without reloading.</p></noscript>
            </div>
        </section>

        <p id="message" role="status" aria-live="polite"></p>
        <section class="card shadow-sm" aria-labelledby="table-title">
            <div class="card-body">
                <h2 class="h5" id="table-title">Products</h2>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <caption>Newest submissions first. Submission times are in UTC.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Product name</th>
                                <th scope="col">Quantity in stock</th>
                                <th scope="col">Price per item</th>
                                <th scope="col">Datetime submitted (UTC)</th>
                                <th scope="col" class="text-end">Total value</th>
                            </tr>
                        </thead>
                        {{-- Render the initial rows; JavaScript refreshes them after saving. --}}
                        <tbody id="products">
                            @forelse ($products as $product)
                                <tr>
                                    <td>{{ $product['name'] }}</td>
                                    <td>{{ $product['quantity'] }}</td>
                                    <td>{{ number_format($product['price_cents'] / 100, 2) }}</td>
                                    <td>{{ $product['submitted_at'] }}</td>
                                    <td class="text-end">{{ number_format($product['total_cents'] / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center">No products yet.</td></tr>
                            @endforelse
                        </tbody>
                        {{-- Display the combined value of all stored products. --}}
                        <tfoot>
                            <tr>
                                <th scope="row" colspan="4">Grand total</th>
                                <td id="grand-total" class="text-end fw-bold">{{ number_format($grand_total_cents / 100, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
