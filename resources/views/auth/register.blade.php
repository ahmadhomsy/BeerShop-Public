@extends('layouts.app')

@section('login')
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white text-center py-4 border-0" style="background: linear-gradient(135deg, var(--bs-dark-header) 0%, var(--bs-dark-malt) 100%) !important;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning p-3 mb-2" style="width: 56px; height: 56px;">
                    <i class="fa-solid fa-user-plus fa-xl text-dark"></i>
                </div>
                <h3 class="fw-bold mb-0 text-white">{{ __('Register') }}</h3>
                <p class="text-white-50 small mb-0 mt-1">{{ session('locale') === 'en' ? 'Create an account to order and enjoy craft beverages.' : 'أنشئ حسابك الجديد للبدء بالتسوق والطلب.' }}</p>
            </div>

            <div class="card-body p-4 p-md-5 bg-white">
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold text-muted small">{{ __('Name') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                            <input id="name" type="text" class="form-control border-start-0 @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="{{ __('Name') }}">
                        </div>
                        @error('name')
                            <span class="text-danger small mt-1 d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-muted small">{{ __('Email Address') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input id="email" type="email" class="form-control border-start-0 @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
                        </div>
                        @error('email')
                            <span class="text-danger small mt-1 d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold text-muted small">{{ __('Password') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input id="password" type="password" class="form-control border-start-0 @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="••••••••">
                        </div>
                        @error('password')
                            <span class="text-danger small mt-1 d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-4">
                        <label for="password-confirm" class="form-label fw-semibold text-muted small">{{ __('Confirm Password') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-shield-halved"></i></span>
                            <input id="password-confirm" type="password" class="form-control border-start-0" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-beer-primary py-2 fs-6">
                            <i class="fa-solid fa-user-check me-2"></i>
                            {{ __('Register') }}
                        </button>
                    </div>

                    <div class="text-center small text-muted mt-3">
                        {{ session('locale') === 'en' ? 'Already have an account?' : 'لديك حساب بالفعل؟' }}
                        <a href="{{ route('login') }}" class="fw-bold text-decoration-none ms-1">
                            {{ __('Login') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
