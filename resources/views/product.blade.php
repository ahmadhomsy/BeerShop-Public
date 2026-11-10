@extends('layouts.master')

@section('content')
<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><i class="fa-solid fa-house"></i> {{ session('locale') === 'en' ? 'Home' : 'الرئيسية' }}</a></li>
            <li class="breadcrumb-item"><a href="/products" class="text-decoration-none">{{ session('locale') === 'en' ? 'Catalog' : 'المنتجات' }}</a></li>
            <li class="breadcrumb-item active text-truncate" aria-current="page" style="max-width: 250px;">
                {{ session()->get('locale') === 'en' ? $product->name : $product->namear }}
            </li>
        </ol>
    </nav>

    <!-- Single Product Detail Card -->
    <div class="single-product-container">
        <div class="row g-4 align-items-center">
            <!-- Left: Gallery Section -->
            <div class="col-lg-5 col-md-6">
                <div class="product-gallery-main">
                    <img id="mainProductImg" src="{{ asset(ltrim($product->imgpath, '/')) }}" alt="{{ $product->name }}" class="img-fluid">
                </div>

                @if (isset($productimgs) && count($productimgs) > 0)
                    <div class="product-gallery-thumbs justify-content-center">
                        <button type="button" class="product-thumb-btn active" onclick="switchProductImg(this, '{{ asset(ltrim($product->imgpath, '/')) }}')">
                            <img src="{{ asset(ltrim($product->imgpath, '/')) }}" alt="Main View">
                        </button>
                        @foreach ($productimgs as $img)
                            <button type="button" class="product-thumb-btn" onclick="switchProductImg(this, '{{ asset(ltrim($img->imgpath, '/')) }}')">
                                <img src="{{ asset(ltrim($img->imgpath, '/')) }}" alt="Thumbnail">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Right: Product Info & Actions -->
            <div class="col-lg-7 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge-stock">
                        <i class="fa-solid fa-circle-check"></i>
                        {{ __('string.prod1') }}: {{ session('locale') === 'en' ? 'In Stock' : 'متوفر' }}
                    </span>

                    @foreach ($allcategories as $item)
                        @if ($item->id === $product->category_id)
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1">
                                <i class="fa-solid fa-layer-group me-1"></i>
                                {{ session()->get('locale') === 'en' ? $item->name : $item->namear }}
                            </span>
                            @foreach ($allsecondcategories as $val)
                                @if ($product->second_category_id === $val->id)
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis px-2 py-1">
                                        {{ session()->get('locale') === 'en' ? $val->name : $val->namear }}
                                    </span>
                                @endif
                            @endforeach
                        @endif
                    @endforeach
                </div>

                <h1 class="fw-bold text-dark mb-2 fs-2">
                    {{ session()->get('locale') === 'en' ? $product->name : $product->namear }}
                </h1>

                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="fs-2 fw-extrabold text-warning-emphasis" style="color: var(--bs-primary-beer) !important;">
                        ${{ number_format($product->price, 2) }}
                    </span>
                    <span class="text-muted small">/ {{ session('locale') === 'en' ? 'unit' : 'قطعة' }}</span>
                </div>

                @if(isset($product->available_stock))
                    <div class="mb-3">
                        @if($product->available_stock > 0)
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6">
                                <i class="fa-solid fa-boxes-stacked me-1"></i>
                                {{ session('locale') === 'en' ? 'In Stock: ' . $product->available_stock : 'المتوفر في المستودع: ' . $product->available_stock }}
                            </span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 fs-6">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                {{ session('locale') === 'en' ? 'Out of Stock' : 'نفذت الكمية من المستودع' }}
                            </span>
                        @endif
                    </div>
                @endif

                <p class="text-muted mb-4 fs-6 lh-base">
                    {{ $product->description }}
                </p>

                <!-- Add to Cart Form (preserving exact form id, action, input names) -->
                <form id="addcartq" action="/addcartq" method="POST" class="mb-4">
                    @csrf
                    <input type="hidden" name="productid" value="{{ $product->id }}">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div style="width: 120px;">
                            <label class="form-label small fw-bold text-muted mb-1">{{ session('locale') === 'en' ? 'Quantity' : 'الكمية' }}</label>
                            <input type="number" name="quantity" class="form-control text-center fw-bold" value="1" min="1" max="99" required>
                        </div>
                        <div class="align-self-end flex-grow-1" style="min-width: 200px;">
                            <button type="submit" class="btn btn-beer-primary w-100 py-2">
                                <i class="fa-solid fa-cart-plus me-2"></i>
                                {{ __('string.showproduct3') }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Social Share -->
                <div class="pt-3 border-top d-flex align-items-center gap-3">
                    <span class="fw-bold text-muted small">{{ __('string.prod3') }}:</span>
                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle" style="width: 36px; height: 36px; padding: 0; display: inline-flex; align-items: center; justify-content: center;"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products: Same Subcategory -->
    @if (isset($productsecondcat) && count($productsecondcat) > 0)
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h3 class="fw-bold fs-4 mb-0 text-dark">
                    <i class="fa-solid fa-sparkles text-warning me-2"></i>
                    {{ __('string.prod6') }}
                </h3>
            </div>
            <div class="row g-4">
                @foreach ($productsecondcat as $item)
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                        <div class="beer-card">
                            <a href="/showproduct/{{ $item->id }}" class="beer-card-img-wrap d-block">
                                <img src="{{ asset(ltrim($item->imgpath, '/')) }}" alt="{{ $item->name }}" loading="lazy">
                            </a>
                            <div class="beer-card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge-stock">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ session('locale') === 'en' ? 'In Stock' : 'متوفر' }}
                                    </span>
                                    <span class="beer-card-price mb-0">${{ number_format($item->price, 2) }}</span>
                                </div>
                                <h4 class="beer-card-title text-truncate">
                                    <a href="/showproduct/{{ $item->id }}" class="text-dark text-decoration-none">
                                        {{ session()->get('locale') === 'en' ? $item->name : $item->namear }}
                                    </a>
                                </h4>
                                <div class="mt-auto pt-3 border-top d-flex gap-2">
                                    <a href="/addcart/{{ $item->id }}" class="btn btn-beer-primary flex-grow-1 btn-sm">
                                        <i class="fa-solid fa-cart-plus me-1"></i>
                                        {{ __('string.showproduct3') }}
                                    </a>
                                    <a href="/showproduct/{{ $item->id }}" class="btn btn-beer-secondary btn-sm px-3" title="Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Related Products: Same Main Category -->
    @if (isset($productcat) && count($productcat) > 0)
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <h3 class="fw-bold fs-4 mb-0 text-dark">
                    <i class="fa-solid fa-layer-group text-warning me-2"></i>
                    {{ __('string.prod6') }}
                </h3>
            </div>
            <div class="row g-4">
                @foreach ($productcat as $item)
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                        <div class="beer-card">
                            <a href="/showproduct/{{ $item->id }}" class="beer-card-img-wrap d-block">
                                <img src="{{ asset(ltrim($item->imgpath, '/')) }}" alt="{{ $item->name }}" loading="lazy">
                            </a>
                            <div class="beer-card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge-stock">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ session('locale') === 'en' ? 'In Stock' : 'متوفر' }}
                                    </span>
                                    <span class="beer-card-price mb-0">${{ number_format($item->price, 2) }}</span>
                                </div>
                                <h4 class="beer-card-title text-truncate">
                                    <a href="/showproduct/{{ $item->id }}" class="text-dark text-decoration-none">
                                        {{ session()->get('locale') === 'en' ? $item->name : $item->namear }}
                                    </a>
                                </h4>
                                <div class="mt-auto pt-3 border-top d-flex gap-2">
                                    <a href="/addcart/{{ $item->id }}" class="btn btn-beer-primary flex-grow-1 btn-sm">
                                        <i class="fa-solid fa-cart-plus me-1"></i>
                                        {{ __('string.showproduct3') }}
                                    </a>
                                    <a href="/showproduct/{{ $item->id }}" class="btn btn-beer-secondary btn-sm px-3" title="Details">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
function switchProductImg(btn, src) {
    const mainImg = document.getElementById('mainProductImg');
    if (mainImg) {
        mainImg.src = src;
    }
    document.querySelectorAll('.product-thumb-btn').forEach(b => b.classList.remove('active'));
    if (btn) {
        btn.classList.add('active');
    }
}
</script>
@endsection
