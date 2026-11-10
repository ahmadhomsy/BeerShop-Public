<!DOCTYPE html>
<html lang="{{ session()->get('locale') ?? 'ar' }}" dir="{{ session()->get('locale') === 'en' ? 'ltr' : 'rtl' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'BeerShop') }} - متجر المشروبات والحرف اليدوية</title>
    
    <link rel="icon" href="{{ asset('images/kkkkk.png') }}" type="image/png">

    <!-- CSS Frameworks & Icons -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">

    <!-- Central Design System -->
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">

    <!-- Local DataTables (No external CDN) -->
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css') }}">

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
                <!-- Search Form (preserving exact form action & input name) -->
                <form class="d-flex mx-lg-3 my-2 my-lg-0 flex-grow-1" action="/search" method="POST" style="max-width: 420px;">
                    @csrf
                    <div class="input-group">
                        <input class="form-control" type="search" name="search" placeholder="{{ session('locale') === 'en' ? 'Search products...' : 'ابحث عن أي منتج...' }}" required>
                        <button class="btn btn-warning" type="submit" style="background-color: var(--bs-primary-beer); border-color: var(--bs-primary-beer); color: #fff;">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </form>

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
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>{{ session('locale') === 'en' ? 'All Products' : 'كافة المنتجات' }}</span>
                        </a>
                    </li>

                    @auth
                        <li class="nav-item">
                            <a class="nav-link" href="/showuserorders">
                                <i class="fa-solid fa-receipt"></i>
                                <span>{{ session('locale') === 'en' ? 'My Orders' : 'طلباتي' }}</span>
                            </a>
                        </li>

                        @if (Auth::user()->role === 'admin')
                            <li class="nav-item">
                                <a class="nav-link text-warning fw-bold" href="/showdash">
                                    <i class="fa-solid fa-gauge-high"></i>
                                    <span>{{ session('locale') === 'en' ? 'Admin Panel' : 'لوحة الإدارة' }}</span>
                                </a>
                            </li>
                        @elseif (Auth::user()->role === 'seller')
                            <li class="nav-item">
                                <a class="nav-link text-warning fw-bold" href="/showproducts">
                                    <i class="fa-solid fa-store"></i>
                                    <span>{{ session('locale') === 'en' ? 'Seller Panel' : 'لوحة التاجر' }}</span>
                                </a>
                            </li>
                        @endif

                        <!-- Cart with Counter -->
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="/showcart">
                                <i class="fa-solid fa-cart-shopping"></i>
                                <span>{{ session('locale') === 'en' ? 'Cart' : 'السلة' }}</span>
                                @if (isset($cartcount) && $cartcount > 0)
                                    <span class="nav-cart-badge ms-1">{{ $cartcount }}</span>
                                @endif
                            </a>
                        </li>

                        <!-- User Profile Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-regular fa-user-circle"></i>
                                <span>{{ Auth::user()->name }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                                <li>
                                    <div class="px-3 py-1 text-muted small">
                                        <i class="fa-solid fa-shield-halved me-1"></i>
                                        {{ ucfirst(Auth::user()->role) }}
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger fw-semibold" href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>
                                        {{ session('locale') === 'en' ? 'Logout' : 'تسجيل الخروج' }}
                                    </a>
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span>{{ session('locale') === 'en' ? 'Login' : 'تسجيل الدخول' }}</span>
                            </a>
                        </li>
                        @if (Route::has('register'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('register') }}">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span>{{ session('locale') === 'en' ? 'Register' : 'إنشاء حساب' }}</span>
                                </a>
                            </li>
                        @endif
                    @endauth

                    <!-- Language Switcher -->
                    <li class="nav-item ms-lg-2">
                        @if (session('locale') === 'en')
                            <form action="{{ route('changelang', 'ar') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning border-0 fw-bold">
                                    <i class="fa-solid fa-globe me-1"></i> عربي
                                </button>
                            </form>
                        @else
                            <form action="{{ route('changelang', 'en') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning border-0 fw-bold">
                                    <i class="fa-solid fa-globe me-1"></i> EN
                                </button>
                            </form>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sub-navigation: Categories Ribbon (Shown if categories exist) -->
    @if (isset($allcategories) && count($allcategories) > 0)
        <div class="subnav-ribbon">
            <div class="container d-flex align-items-center">
                <span class="fw-bold me-2 small text-muted text-nowrap">
                    <i class="fa-solid fa-tags text-warning"></i>
                    {{ session('locale') === 'en' ? 'Categories:' : 'التصنيفات:' }}
                </span>
                <div class="d-flex overflow-auto flex-grow-1 py-1">
                    <a href="/products" class="cat-pill {{ !isset($id) ? 'active' : '' }}">
                        {{ session('locale') === 'en' ? 'All' : 'الكل' }}
                    </a>
                    @foreach ($allcategories as $cat)
                        <a href="/products/{{ $cat->id }}" class="cat-pill {{ (isset($id) && $id == $cat->id) ? 'active' : '' }}">
                            {{ session('locale') === 'en' ? $cat->name : $cat->namear }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Flash Messages / Feedback Alerts -->
    <div class="container mt-3">
        @if (session('success'))
            <div class="alert alert-beer alert-beer-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check fs-5"></i>
                <div class="flex-grow-1">{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-beer alert-beer-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                <div class="flex-grow-1">{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-beer alert-beer-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <div class="flex-grow-1">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
    </div>

    <!-- Main Content Injection -->
    <main class="main-content-wrapper">
        @yield('content')
    </main>

    <!-- Unified Footer -->
    <footer class="beershop-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <img src="{{ asset('images/kkkk.jpg') }}" alt="BeerShop" style="height: 38px; width: 38px; border-radius: 8px;">
                        <span class="fs-4 fw-bold text-white"><span class="text-warning">Beer</span>Shop</span>
                    </div>
                    <p class="small text-muted mb-3" style="color: #bfaea0 !important;">
                        {{ session('locale') === 'en' 
                            ? 'Your premier destination for authentic craft brews, premium beverages, and artisanal products with atomic stock assurance.' 
                            : 'وجهتكم الرائدة لأجود أنواع المشروبات الحرفية والمنتجات المتميزة مع ضمان دقة المخزون الفوري وعمليات الشحن الموثوقة.' }}
                    </p>
                    <div class="d-flex gap-2">
                        <span class="badge bg-dark border border-secondary text-light px-3 py-2">
                            <i class="fa-solid fa-truck-fast text-warning me-1"></i>
                            {{ session('locale') === 'en' ? 'Fast Delivery' : 'توصيل سريع' }}
                        </span>
                        <span class="badge bg-dark border border-secondary text-light px-3 py-2">
                            <i class="fa-solid fa-lock text-warning me-1"></i>
                            {{ session('locale') === 'en' ? 'Secure Payment' : 'دفع آمن' }}
                        </span>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h5>{{ session('locale') === 'en' ? 'Quick Links' : 'روابط سريعة' }}</h5>
                    <ul class="list-unstyled d-flex flex-column gap-2 small">
                        <li><a href="/"><i class="fa-solid fa-angle-left me-1"></i> {{ session('locale') === 'en' ? 'Home' : 'الرئيسية' }}</a></li>
                        <li><a href="/products"><i class="fa-solid fa-angle-left me-1"></i> {{ session('locale') === 'en' ? 'Catalog' : 'المتجر' }}</a></li>
                        <li><a href="/showcart"><i class="fa-solid fa-angle-left me-1"></i> {{ session('locale') === 'en' ? 'Cart' : 'سلة المشتريات' }}</a></li>
                        <li><a href="/showuserorders"><i class="fa-solid fa-angle-left me-1"></i> {{ session('locale') === 'en' ? 'Order Tracking' : 'متابعة الطلبات' }}</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h5>{{ session('locale') === 'en' ? 'Customer Care' : 'خدمة العملاء' }}</h5>
                    <ul class="list-unstyled d-flex flex-column gap-2 small" style="color: #bfaea0;">
                        <li><i class="fa-solid fa-headset text-warning me-2"></i> {{ session('locale') === 'en' ? 'Support 24/7' : 'الدعم الفني على مدار الساعة' }}</li>
                        <li><i class="fa-solid fa-shield-check text-warning me-2"></i> {{ session('locale') === 'en' ? '100% Quality Guaranteed' : 'جودة مضمونة 100%' }}</li>
                        <li><i class="fa-solid fa-rotate-left text-warning me-2"></i> {{ session('locale') === 'en' ? 'Easy Returns' : 'سياسة إرجاع مرنة' }}</li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h5>{{ session('locale') === 'en' ? 'Working Hours' : 'أوقات العمل' }}</h5>
                    <p class="small mb-2" style="color: #bfaea0;">
                        {{ session('locale') === 'en' ? 'Daily: 10:00 AM - 12:00 Midnight' : 'يومياً: من 10:00 صباحاً حتى 12:00 منتصف الليل' }}
                    </p>
                    <div class="d-flex gap-3 fs-5 mt-3">
                        <a href="#" class="text-secondary"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#" class="text-secondary"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" class="text-secondary"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#" class="text-secondary"><i class="fa-brands fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>

            <div class="beershop-footer-bottom">
                <p class="mb-0">
                    &copy; {{ date('Y') }} <strong>BeerShop</strong>. {{ session('locale') === 'en' ? 'All Rights Reserved.' : 'جميع الحقوق محفوظة.' }}
                </p>
            </div>
        </div>
    </footer>

    <!-- Core Scripts -->
    <script src="{{ asset('assets/js/jquery-1.11.3.min.js') }}"></script>
    <script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/jquery.dataTables.min.js') }}"></script>

    @stack('scripts')
</body>
</html>
