@extends('layouts.master')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h2 fw-bold text-dark mb-0">
                <i class="fa-solid fa-truck-fast text-warning me-2"></i>
                {{ session('locale') === 'en' ? 'Checkout' : 'إتمام الطلب' }}
            </h1>
            <p class="text-muted small mt-1 mb-0">
                {{ session('locale') === 'en' ? 'Complete your billing details and confirm your purchase' : 'يرجى إدخال بيانات التوصيل وإتمام الشراء' }}
            </p>
        </div>
        <a href="/showcart" class="btn btn-sm btn-beer-secondary">
            <i class="fa-solid fa-cart-shopping me-1"></i>
            {{ session('locale') === 'en' ? 'Back to Cart' : 'العودة للسلة' }}
        </a>
    </div>

    <div class="row g-4">
        <!-- Billing Details & Review Column -->
        <div class="col-lg-8">
            <div class="accordion" id="checkoutAccordion">
                <!-- 1. Billing Address -->
                <div class="card mb-3 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom" id="headingBilling">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="fa-solid fa-location-dot text-warning me-2"></i>
                            {{ __('string.mainout1') }}
                        </h2>
                    </div>
                    <div id="collapseBilling" class="collapse show" aria-labelledby="headingBilling">
                        <div class="card-body p-4">
                            <form id="storeorder" action="/storeorder" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted">{{ __('string.address1') }} *</label>
                                        <input required type="text" name="name" class="form-control" placeholder="{{ __('string.address1') }}" value="{{ old('name') }}">
                                        @error('name')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted">{{ __('string.address2') }} *</label>
                                        <input required type="email" name="email" class="form-control" placeholder="{{ __('string.address2') }}" value="{{ old('email') }}">
                                        @error('email')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted">{{ __('string.address3') }} *</label>
                                        <input required type="text" name="address" class="form-control" placeholder="{{ __('string.address3') }}" value="{{ old('address') }}">
                                        @error('address')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold small text-muted">{{ __('string.address4') }} *</label>
                                        <input required type="tel" name="phone" class="form-control" placeholder="{{ __('string.address4') }}" value="{{ old('phone') }}">
                                        @error('phone')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold small text-muted">{{ __('string.address5') }}</label>
                                        <textarea name="note" id="bill" rows="3" class="form-control" placeholder="{{ __('string.address5') }}">{{ old('note') }}</textarea>
                                        @error('note')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 2. Review Cart Items -->
                <div class="card mb-3 border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom" id="headingCartReview">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="fa-solid fa-box text-warning me-2"></i>
                            {{ __('string.mainout2') }}
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">{{ __('string.cart1') }}</th>
                                        <th scope="col">{{ __('string.cart2') }}</th>
                                        <th scope="col">{{ __('string.cart3') }}</th>
                                        <th scope="col" class="text-center">{{ __('string.cart4') }}</th>
                                        <th scope="col" class="text-end">{{ __('string.cart5') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($usercart as $item)
                                        <tr>
                                            <td style="width: 70px;">
                                                <img src="{{ asset(ltrim($item->product->imgpath, '/')) }}" alt="{{ $item->product->name }}" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                            </td>
                                            <td class="fw-semibold">
                                                {{ session()->get('locale') === 'en' ? $item->product->name : $item->product->namear }}
                                            </td>
                                            <td class="text-muted">
                                                ${{ number_format($item->product->price, 2) }}
                                            </td>
                                            <td class="text-center fw-bold">
                                                {{ $item->quantity }}
                                            </td>
                                            <td class="text-end fw-bold">
                                                ${{ number_format($item->product->price * $item->quantity, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. Payment Method -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="fa-solid fa-credit-card text-warning me-2"></i>
                            {{ __('string.mainout3') }}
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex flex-column gap-3">
                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="paymentOption" id="cashRadio" value="cash" checked onclick="togglePaymentForm(false)">
                                <label class="form-check-label fw-bold ms-2" for="cashRadio">
                                    <i class="fa-solid fa-money-bill-wave text-success me-1"></i>
                                    {{ __('string.pay1') }}
                                </label>
                                <div class="text-muted small ms-4">{{ session('locale') === 'en' ? 'Pay directly with cash upon product arrival' : 'الدفع نقداً عند استلام الشحنة' }}</div>
                            </div>

                            <div class="form-check p-3 border rounded-3 bg-light">
                                <input class="form-check-input" type="radio" name="paymentOption" id="cardRadio" value="card" onclick="togglePaymentForm(true)">
                                <label class="form-check-label fw-bold ms-2" for="cardRadio">
                                    <i class="fa-brands fa-cc-visa text-primary me-1"></i>
                                    {{ __('string.pay2') }}
                                </label>
                                <div class="text-muted small ms-4">{{ session('locale') === 'en' ? 'Pay securely online with credit or debit card' : 'الدفع الآمن عبر البطاقات الإلكترونية' }}</div>
                            </div>

                            <!-- Optional Card Form Display -->
                            <div id="cardFields" class="p-3 border rounded-3 bg-white mt-2" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold text-muted">{{ __('string.pay21') }}</label>
                                        <input type="text" class="form-control" maxlength="16" placeholder="{{ __('string.pay211') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">{{ __('string.pay22') }}</label>
                                        <input type="text" class="form-control" maxlength="5" placeholder="{{ __('string.pay221') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-muted">{{ __('string.pay23') }}</label>
                                        <input type="text" class="form-control" maxlength="3" placeholder="{{ __('string.pay231') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary Column -->
        <div class="col-lg-4">
            <div class="order-summary-card position-sticky" style="top: 90px;">
                <h4 class="fw-bold fs-5 mb-3 text-dark pb-2 border-bottom">
                    {{ __('string.adout1') }}
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

                <div class="mt-4">
                    <button type="button" class="btn btn-beer-primary w-100 py-3 fs-6" onclick="document.getElementById('storeorder').submit();">
                        <i class="fa-solid fa-lock me-2"></i>
                        {{ __('string.adout2') }}
                    </button>
                    <div class="text-center text-muted small mt-2">
                        <i class="fa-solid fa-shield-halved text-success me-1"></i>
                        {{ session('locale') === 'en' ? 'Encrypted & Guaranteed Checkout' : 'دفع آمن ومعاملة موثوقة ومحمية' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePaymentForm(showCard) {
    const cardFields = document.getElementById('cardFields');
    if (cardFields) {
        cardFields.style.display = showCard ? 'block' : 'none';
    }
}
</script>
@endsection
