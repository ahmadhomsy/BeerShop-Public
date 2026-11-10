@extends('layouts.app')

@section('login')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-header bg-dark text-white text-center py-4 border-0" style="background: linear-gradient(135deg, var(--bs-dark-header) 0%, var(--bs-dark-malt) 100%) !important;">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning p-3 mb-2" style="width: 56px; height: 56px;">
                    <i class="fa-solid fa-beer-mug-empty fa-xl text-dark"></i>
                </div>
                <h3 class="fw-bold mb-0 text-white">{{ __('Login') }}</h3>
                <p class="text-white-50 small mb-0 mt-1">{{ session('locale') === 'en' ? 'Welcome back! Sign in to continue.' : 'مرحباً بك مجدداً! سجل دخولك للمتابعة.' }}</p>
            </div>

            <div class="card-body p-4 p-md-5 bg-white">
                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-muted small">{{ __('Email Address') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-envelope"></i></span>
                            <input id="email" type="email" class="form-control border-start-0 @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="you@example.com">
                        </div>
                        @error('email')
                            <span class="text-danger small mt-1 d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold text-muted small mb-0">{{ __('Password') }}</label>
                            @if (Route::has('password.request'))
                                <a class="small text-decoration-none" href="{{ route('password.request') }}">
                                    {{ __('Forgot Your Password?') }}
                                </a>
                            @endif
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input id="password" type="password" class="form-control border-start-0 @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="••••••••">
                        </div>
                        @error('password')
                            <span class="text-danger small mt-1 d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <label class="form-check-label small text-muted" for="remember">
                                {{ __('Remember Me') }}
                            </label>
                        </div>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-beer-primary py-2 fs-6">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>
                            {{ __('Login') }}
                        </button>
                    </div>

                    @if (Route::has('register'))
                        <div class="text-center small text-muted mt-3">
                            {{ session('locale') === 'en' ? "Don't have an account?" : 'ليس لديك حساب بعد؟' }}
                            <a href="{{ route('register') }}" class="fw-bold text-decoration-none ms-1">
                                {{ __('Register') }}
                            </a>
                        </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
