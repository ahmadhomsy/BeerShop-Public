<!DOCTYPE html>
<html lang="{{ session()->get('locale') ?? 'ar' }}" dir="{{ session()->get('locale') === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'BeerShop') }} - {{ session('locale') === 'en' ? 'Authentication' : 'تسجيل الدخول والتسجيل' }}</title>
    
    <link rel="icon" href="{{ asset('images/kkkkk.png') }}" type="image/png">

    <!-- CSS Frameworks & Icons -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

    @stack('styles')
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-beershop">
        <div class="container">
            <!-- Brand Logo -->
            <a class="navbar-brand" href="/">
                <img src="{{ asset('images/kkkk.jpg') }}" alt="BeerShop Logo">
                <span>Beer</span>Shop
            </a>

            <!-- Mobile Hamburger Toggle -->
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#beershopNavbar" aria-controls="beershopNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa-solid fa-bars fa-lg text-warning"></i>
            </button>

            <!-- Navbar Links & Controls -->
            <div class="collapse navbar-collapse" id="beershopNavbar">
                <!-- Navigation Links -->
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="/">
                            <i class="fa-solid fa-house"></i>
                            <span>{{ session('locale') === 'en' ? 'Home' : 'الرئيسية' }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/products">
                            <i class="fa-solid fa-beer-mug-empty"></i>
                            <span>{{ session('locale') === 'en' ? 'Catalog' : 'كافة المنتجات' }}</span>
                        </a>
                    </li>

                    <!-- Language Switcher -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-globe"></i>
                            <span>{{ session('locale') === 'en' ? 'English' : 'العربية' }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="langDropdown">
                            <li><a class="dropdown-item" href="/lang/ar"><i class="fa-solid fa-check me-2 {{ session('locale') !== 'en' ? 'text-warning' : 'text-transparent' }}"></i> العربية (RTL)</a></li>
                            <li><a class="dropdown-item" href="/lang/en"><i class="fa-solid fa-check me-2 {{ session('locale') === 'en' ? 'text-warning' : 'text-transparent' }}"></i> English (LTR)</a></li>
                        </ul>
                    </li>

                    @guest
                        @if (Route::has('login'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">
                                    <i class="fa-solid fa-right-to-bracket"></i>
                                    <span>{{ session('locale') === 'en' ? 'Sign In' : 'تسجيل الدخول' }}</span>
                                </a>
                            </li>
                        @endif
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span>{{ session('locale') === 'en' ? 'Register' : 'إنشاء حساب' }}</span>
                                </a>
                            </li>
                        @endif
                    @else
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userMenuDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user text-warning"></i>
                                <span>{{ Auth::user()->name }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuDropdown">
                                <li>
                                    <a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-2 text-danger"></i>
                                        {{ session('locale') === 'en' ? 'Logout' : 'تسجيل الخروج' }}
                                    </a>
                                </li>
                            </ul>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </li>
                    @endguest
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content-wrapper py-5">
        <div class="container">
            @yield('login')
            @yield('content')
        </div>
    </main>

    <!-- Footer -->
    <footer class="beershop-footer mt-auto">
        <div class="container">
            <div class="beershop-footer-bottom">
                <p class="mb-0">&copy; {{ date('Y') }} BeerShop. {{ session('locale') === 'en' ? 'All rights reserved.' : 'جميع الحقوق محفوظة.' }}</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
