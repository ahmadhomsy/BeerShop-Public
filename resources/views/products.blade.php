@extends('layouts.master')

@section('content')
<div class="container py-4">
    <!-- Breadcrumb & Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            @if (!empty($categories) && (is_countable($categories) ? count($categories) > 0 : true))
                @foreach ($categories as $item)
                    <div class="d-inline-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1">
                            <i class="fa-solid fa-tag me-1"></i> {{ __('string.header3') }}
                        </span>
                        <h1 class="h2 fw-bold text-dark mb-0">
                            {{ session()->get('locale') === 'en' ? $item->name : $item->namear }}
                        </h1>
                    </div>
                @endforeach
            @else
                <h1 class="h2 fw-bold text-dark mb-0">
                    <i class="fa-solid fa-beer-mug-empty text-warning me-2"></i>
                    {{ __('string.header4') }}
                </h1>
            @endif
            <p class="text-muted small mt-1 mb-0">
                {{ session('locale') === 'en' ? 'Browse our handcrafted selection of premium drinks and items' : 'تصفح تشكيلتنا المختارة بعناية من المشروبات والحرف اليدوية' }}
            </p>
        </div>

        <div>
            <span class="badge bg-secondary-subtle text-secondary-emphasis px-3 py-2 fs-6">
                {{ count($products) }} {{ session('locale') === 'en' ? 'Items found' : 'منتج متوفر' }}
            </span>
        </div>
    </div>

    <!-- Products Grid -->
    @if (count($products) === 0)
        <div class="text-center py-5">
            <div class="mb-3 text-warning">
                <i class="fa-solid fa-box-open fa-3x"></i>
            </div>
            <h4 class="fw-bold">{{ session('locale') === 'en' ? 'No products available in this selection' : 'لا توجد منتجات متوفرة في هذا القسم حالياً' }}</h4>
            <p class="text-muted">{{ session('locale') === 'en' ? 'Check back soon or explore other categories.' : 'يرجى استكشاف فئات أخرى أو العودة لاحقاً.' }}</p>
            <a href="/" class="btn btn-beer-primary mt-2">
                <i class="fa-solid fa-house me-1"></i> {{ session('locale') === 'en' ? 'Back to Home' : 'العودة للرئيسية' }}
            </a>
        </div>
    @else
        <div class="row g-4">
            @foreach ($products as $item)
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
                            <p class="text-muted small text-truncate mb-3" title="{{ $item->description }}">
                                {{ $item->description }}
                            </p>
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
    @endif
</div>
@endsection
