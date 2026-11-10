@extends('layouts.master')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h2 fw-bold text-dark mb-0">
                <i class="fa-solid fa-cart-shopping text-warning me-2"></i>
                {{ session('locale') === 'en' ? 'Shopping Cart' : 'سلة المشتريات' }}
            </h1>
            <p class="text-muted small mt-1 mb-0">
                {{ session('locale') === 'en' ? 'Review your selected items and proceed to checkout' : 'راجع المنتجات المحددة وتابع لإتمام الطلب' }}
            </p>
        </div>
        <a href="/products" class="btn btn-sm btn-beer-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>
            {{ session('locale') === 'en' ? 'Continue Shopping' : 'متابعة التسوق' }}
        </a>
    </div>

    <!-- Flash message from session -->
    @if (session('message'))
        <div class="alert alert-beer alert-beer-danger mb-4">
            <i class="fa-solid fa-circle-exclamation fa-lg"></i>
            <div>{{ session('message') }}</div>
        </div>
    @endif

    @if (!empty($mess))
        <div class="alert alert-beer alert-beer-danger mb-4">
            <i class="fa-solid fa-circle-exclamation fa-lg"></i>
            <div>{{ $mess }}</div>
        </div>
    @endif

    @if (count($usercart) === 0)
        <div class="card p-5 text-center border-0 shadow-sm rounded-4 my-4">
            <div class="mb-3 text-warning">
                <i class="fa-solid fa-cart-arrow-down fa-4x opacity-50"></i>
            </div>
            <h3 class="fw-bold mb-2">{{ session('locale') === 'en' ? 'Your cart is empty' : 'سلة التسوق فارغة' }}</h3>
            <p class="text-muted mb-4">{{ session('locale') === 'en' ? 'Explore our catalog to discover fine drinks and craft items.' : 'استكشف متجرنا وتصفح أفضل المشروبات والمنتجات المتنوعة.' }}</p>
            <div>
                <a href="/products" class="btn btn-beer-primary px-4 py-2">
                    <i class="fa-solid fa-bag-shopping me-1"></i> {{ session('locale') === 'en' ? 'Start Shopping' : 'ابدأ التسوق الآن' }}
                </a>
            </div>
        </div>
    @else
        <div class="row g-4">
            <!-- Products Table Column -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 50px;"></th>
                                    <th scope="col">{{ __('string.cart1') }}</th>
                                    <th scope="col">{{ __('string.cart2') }}</th>
                                    <th scope="col">{{ __('string.cart3') }}</th>
                                    <th scope="col" class="text-center" style="width: 140px;">{{ __('string.cart4') }}</th>
                                    <th scope="col" class="text-end">{{ __('string.cart5') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($usercart as $item)
                                    <tr>
                                        <!-- Remove Action -->
                                        <td class="text-center">
                                            <a href="/deletecart/{{ $item->product->id }}" class="text-danger p-1" title="Remove Item">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </td>
                                        <!-- Image -->
                                        <td style="width: 80px;">
                                            <a href="/showproduct/{{ $item->product->id }}">
                                                <img src="{{ asset(ltrim($item->product->imgpath, '/')) }}" alt="{{ $item->product->name }}" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
                                            </a>
                                        </td>
                                        <!-- Name -->
                                        <td>
                                            <a href="/showproduct/{{ $item->product->id }}" class="text-dark fw-bold text-decoration-none d-block">
                                                {{ session()->get('locale') === 'en' ? $item->product->name : $item->product->namear }}
                                            </a>
                                            <span class="small text-muted">{{ session('locale') === 'en' ? 'In Stock' : 'متوفر' }}</span>
                                        </td>
                                        <!-- Unit Price -->
                                        <td class="fw-semibold text-muted">
                                            ${{ number_format($item->product->price, 2) }}
                                        </td>
                                        <!-- Quantity Controls (+ / -) -->
                                        <td class="text-center">
                                            <div class="input-group input-group-sm justify-content-center" style="max-width: 110px; margin: 0 auto;">
                                                <a href="/deleteonecart/{{ $item->product->id }}" class="btn btn-outline-secondary px-2" title="Decrease">
                                                    <i class="fa-solid fa-minus fa-xs"></i>
                                                </a>
                                                <span class="input-group-text bg-white fw-bold px-3">
                                                    {{ $item->quantity }}
                                                </span>
                                                <a href="/addcart/{{ $item->product->id }}" class="btn btn-outline-secondary px-2" title="Increase">
                                                    <i class="fa-solid fa-plus fa-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <!-- Subtotal -->
                                        <td class="text-end fw-bold text-warning-emphasis" style="color: var(--bs-primary-beer) !important;">
                                            ${{ number_format($item->product->price * $item->quantity, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                        <a href="/clearallcart" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ session('locale') === 'en' ? 'Are you sure you want to empty your cart?' : 'هل أنت متأكد من تفريغ السلة بالكامل؟' }}')">
                            <i class="fa-solid fa-trash-arrow-up me-1"></i>
                            {{ __('string.cart8') }}
                        </a>
                        <span class="text-muted small">
                            {{ count($usercart) }} {{ session('locale') === 'en' ? 'distinct items' : 'عناصر مختلفة' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Order Summary Column -->
            <div class="col-lg-4">
                <div class="order-summary-card">
                    <h4 class="fw-bold fs-5 mb-3 text-dark pb-2 border-bottom">
                        {{ session('locale') === 'en' ? 'Order Summary' : 'ملخص الطلب' }}
                    </h4>

                    <div class="summary-item">
                        <span class="text-muted">{{ __('string.cart6') }}</span>
                        <span class="fw-bold">${{ number_format($total, 2) }}</span>
                    </div>

                    <div class="summary-item">
                        <span class="text-muted">{{ __('string.cart7') }}</span>
                        <span class="fw-bold">$15.00</span>
                    </div>

                    <div class="summary-item total-item">
                        <span>{{ __('string.cart5') }}</span>
                        <span>
                            @if ($total == 0)
                                $0.00
                            @else
                                ${{ number_format($total + 15, 2) }}
                            @endif
                        </span>
                    </div>

                    <div class="mt-4 d-grid gap-2">
                        <a href="/checkout" class="btn btn-beer-primary py-2 fs-6">
                            <i class="fa-solid fa-credit-card me-2"></i>
                            {{ __('string.cart9') }}
                        </a>
                        <a href="/products" class="btn btn-beer-secondary py-2">
                            {{ session('locale') === 'en' ? 'Add More Products' : 'إضافة المزيد من المنتجات' }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
