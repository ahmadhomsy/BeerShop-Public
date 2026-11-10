@extends('layouts.master')

@section('content')
<div class="container py-4">

    <!-- 1. Hero Carousel Banner -->
    @if (isset($sliderimage) && count($sliderimage) > 0)
        <div id="heroCarousel" class="carousel slide carousel-fade hero-slider-wrap" data-bs-ride="carousel">
            <div class="carousel-indicators">
                @foreach ($sliderimage as $idx => $item)
                    <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $idx }}" class="{{ $idx === 0 ? 'active' : '' }}" aria-current="{{ $idx === 0 ? 'true' : 'false' }}" aria-label="Slide {{ $idx + 1 }}"></button>
                @endforeach
            </div>
            <div class="carousel-inner">
                @foreach ($sliderimage as $idx => $item)
                    <div class="carousel-item {{ $idx === 0 ? 'active' : '' }}" data-bs-interval="4500">
                        <img src="{{ asset(ltrim($item->imgpath, '/')) }}" class="d-block w-100" alt="{{ $item->slider->name ?? 'Hero Slide' }}" loading="lazy">
                        <div class="carousel-caption d-md-block text-center">
                            <span class="badge bg-warning text-dark px-3 py-1 mb-2 fw-bold text-uppercase">
                                <i class="fa-solid fa-beer-mug-empty me-1"></i> Special Offer
                            </span>
                            <h3 class="fw-bold text-white mb-2 fs-2">{{ $item->slider->description }}</h3>
                            <a href="/products" class="btn btn-beer-primary mt-2">
                                <i class="fa-solid fa-bag-shopping me-1"></i>
                                {{ session('locale') === 'en' ? 'Shop Deals Now' : 'تسوق العروض الآن' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    @endif

    <!-- 2. Latest Featured Products -->
    <section class="mb-5">
        <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-2">
            <div>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 mb-1">
                    <i class="fa-solid fa-sparkles me-1"></i> {{ session('locale') === 'en' ? 'Fresh Brews' : 'أحدث الإضافات' }}
                </span>
                <h2 class="fw-bold mb-0 text-dark">
                    <span style="color: var(--bs-primary-beer);">{{ __('string.header1') }}</span> {{ __('string.header11') }}
                </h2>
                <p class="text-muted small mb-0 mt-1">{{ __('string.header12') }}</p>
            </div>
            <a href="/products" class="btn btn-sm btn-beer-secondary text-nowrap">
                {{ session('locale') === 'en' ? 'View Catalog' : 'عرض الكل' }} <i class="fa-solid fa-arrow-left ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            @foreach ($lastproducts as $item)
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
                            <h3 class="beer-card-title text-truncate">
                                <a href="/showproduct/{{ $item->id }}" class="text-dark text-decoration-none">
                                    {{ session()->get('locale') === 'en' ? $item->name : $item->namear }}
                                </a>
                            </h3>
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

    <!-- 3. Explore Categories Grid -->
    <section class="mb-5">
        <div class="text-center mb-4">
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 mb-2">
                <i class="fa-solid fa-layer-group me-1"></i> {{ session('locale') === 'en' ? 'Explore by Taste' : 'تصفح حسب الذوق' }}
            </span>
            <h2 class="fw-bold mb-1">{{ __('string.header2') }}</h2>
            <p class="text-muted small">{{ session('locale') === 'en' ? 'Select your preferred category to browse curated selections' : 'اختر تصنيفك المفضل لاستكشاف أروع المشروبات والمنتجات المتنوعة' }}</p>
        </div>

        <div class="row g-3">
            @foreach ($allcategories as $cat)
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-6">
                    <a href="/products/{{ $cat->id }}" class="beer-card text-decoration-none text-center p-3 h-100">
                        <div class="rounded-circle mx-auto mb-3 overflow-hidden d-flex align-items-center justify-content-center" style="width: 88px; height: 88px; background: #faf5ee; border: 2px solid var(--bs-border-subtle);">
                            <img src="{{ asset(ltrim($cat->imgpath, '/')) }}" alt="{{ $cat->name }}" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block';" loading="lazy">
                            <i class="fa-solid fa-layer-group fa-2x text-warning" style="display: none;"></i>
                        </div>
                        <h4 class="fs-6 fw-bold text-dark mb-1">
                            {{ session()->get('locale') === 'en' ? $cat->name : $cat->namear }}
                        </h4>
                        <span class="small text-muted">
                            {{ session('locale') === 'en' ? 'Explore' : 'تصفح الفئة' }} &rarr;
                        </span>
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <!-- 4. Categorized Product Showcases -->
    @foreach ($allcategories as $cat)
        @php
            $catProducts = $products->where('category_id', $cat->id)->take(4);
        @endphp
        @if ($catProducts->count() > 0)
            <section class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="fw-bold fs-4 mb-0 text-dark">
                        <i class="fa-solid fa-beer-mug-empty text-warning me-2"></i>
                        <span>{{ session()->get('locale') === 'en' ? $cat->name : $cat->namear }}</span>
                        <span class="text-muted fw-normal fs-6">({{ __('string.header3') }})</span>
                    </h3>
                    <a href="/products/{{ $cat->id }}" class="btn btn-sm btn-beer-secondary">
                        {{ session('locale') === 'en' ? 'More in' : 'المزيد في' }} {{ session()->get('locale') === 'en' ? $cat->name : $cat->namear }} &rarr;
                    </a>
                </div>

                <div class="row g-4">
                    @foreach ($catProducts as $product)
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                            <div class="beer-card">
                                <a href="/showproduct/{{ $product->id }}" class="beer-card-img-wrap d-block">
                                    <img src="{{ asset(ltrim($product->imgpath, '/')) }}" alt="{{ $product->name }}" loading="lazy">
                                </a>
                                <div class="beer-card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge-stock">
                                            <i class="fa-solid fa-circle-check"></i>
                                            {{ session('locale') === 'en' ? 'In Stock' : 'متوفر' }}
                                        </span>
                                        <span class="beer-card-price mb-0">${{ number_format($product->price, 2) }}</span>
                                    </div>
                                    <h4 class="beer-card-title text-truncate">
                                        <a href="/showproduct/{{ $product->id }}" class="text-dark text-decoration-none">
                                            {{ session()->get('locale') === 'en' ? $product->name : $product->namear }}
                                        </a>
                                    </h4>
                                    <div class="mt-auto pt-3 border-top d-flex gap-2">
                                        <a href="/addcart/{{ $product->id }}" class="btn btn-beer-primary flex-grow-1 btn-sm">
                                            <i class="fa-solid fa-cart-plus me-1"></i>
                                            {{ __('string.showproduct3') }}
                                        </a>
                                        <a href="/showproduct/{{ $product->id }}" class="btn btn-beer-secondary btn-sm px-3" title="Details">
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
    @endforeach

</div>
@endsection
