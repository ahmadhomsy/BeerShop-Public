<!DOCTYPE html>
<html lang="{{ session()->get('locale') ?? 'ar' }}" dir="{{ session()->get('locale') === 'en' ? 'ltr' : 'rtl' }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>BeerShop - لوحة تحكم المدير</title>
    
    <link rel="icon" href="{{ asset('images/kkkkk.png') }}" type="image/png">

    <!-- Vendors styles-->
    <link rel="stylesheet" href="{{ asset('dashb/vendors/simplebar/css/simplebar.css') }}">
    <link rel="stylesheet" href="{{ asset('dashb/css/vendors/simplebar.css') }}">
    <link rel="stylesheet" href="{{ asset('dashb/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">

    <!-- Central Design System & Local DataTables -->
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">

    <style>
        .logoimg {
            width: 54px;
            height: 54px;
            border-radius: 8px;
            margin: 8px;
            object-fit: cover;
            border: 1px solid rgba(217, 119, 6, 0.4);
        }
        .sidebar-brand {
            padding: 1rem;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="sidebar sidebar-dark sidebar-fixed" id="sidebar">
        <div class="sidebar-brand d-none d-md-flex align-items-center">
            <img class="logoimg" src="{{ asset('images/kkkkk.png') }}" alt="BeerShop Admin">
            <span class="fs-5 fw-bold text-white"><span class="text-warning">Beer</span>Shop</span>
        </div>
        <ul class="sidebar-nav" data-coreui="navigation" data-simplebar="">
            <li class="nav-item">
                <a class="nav-link" href="/showdash">
                    <i class="fa-solid fa-gauge-high me-2 text-warning"></i>
                    <span>لوحة التحكم الرئيسية</span>
                </a>
            </li>
            <li class="nav-title">إدارة المستخدمين</li>
            <li class="nav-item">
                <a class="nav-link" href="/showusers/2">
                    <i class="fa-solid fa-store me-2"></i> التجار (Sellers)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/showusers/3">
                    <i class="fa-solid fa-users me-2"></i> الزبائن (Customers)
                </a>
            </li>

            <li class="nav-title">إدارة الطلبات والمبيعات</li>
            <li class="nav-group">
                <a class="nav-link nav-group-toggle" href="#">
                    <i class="fa-solid fa-receipt me-2"></i> الطلبات
                </a>
                <ul class="nav-group-items">
                    <li class="nav-item"><a class="nav-link" href="/mainorders"><i class="fa-solid fa-circle-dot me-2 small"></i>كافة الطلبات الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="/sellerorders"><i class="fa-solid fa-circle-dot me-2 small"></i>طلبات التجار</a></li>
                    <li class="nav-item"><a class="nav-link" href="/finishedorders"><i class="fa-solid fa-circle-check me-2 small text-success"></i>الطلبات المنتهية</a></li>
                </ul>
            </li>

            <li class="nav-title">إدارة المنتجات والتصنيفات</li>
            <li class="nav-group">
                <a class="nav-link nav-group-toggle" href="#">
                    <i class="fa-solid fa-boxes-stacked me-2"></i> المنتجات
                </a>
                <ul class="nav-group-items">
                    <li class="nav-item"><a class="nav-link" href="/adminshowproducts"><i class="fa-solid fa-list me-2 small"></i>عرض المنتجات</a></li>
                    <li class="nav-item"><a class="nav-link" href="/adminshowlastproducts"><i class="fa-solid fa-clock-rotate-left me-2 small"></i>أحدث المنتجات</a></li>
                    <li class="nav-item"><a class="nav-link" href="/adminaddproduct"><i class="fa-solid fa-plus me-2 small text-warning"></i>إضافة منتج</a></li>
                </ul>
            </li>

            <li class="nav-group">
                <a class="nav-link nav-group-toggle" href="#">
                    <i class="fa-solid fa-layer-group me-2"></i> التصنيفات
                </a>
                <ul class="nav-group-items">
                    <li class="nav-item"><a class="nav-link" href="/adminshowcategories"><i class="fa-solid fa-folder me-2 small"></i>التصنيفات الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="/adminshowsecondcategories"><i class="fa-solid fa-folder-tree me-2 small"></i>التصنيفات الفرعية</a></li>
                    <li class="nav-item"><a class="nav-link" href="/adminaddcat"><i class="fa-solid fa-folder-plus me-2 small text-warning"></i>إضافة تصنيف رئيسي</a></li>
                    <li class="nav-item"><a class="nav-link" href="/adminaddscat"><i class="fa-solid fa-plus me-2 small text-warning"></i>إضافة تصنيف فرعي</a></li>
                </ul>
            </li>

            <li class="nav-group">
                <a class="nav-link nav-group-toggle" href="#">
                    <i class="fa-solid fa-cart-shopping me-2"></i> السلات الجارية
                </a>
                <ul class="nav-group-items">
                    <li class="nav-item"><a class="nav-link" href="/admingetcostemercarts"><i class="fa-solid fa-cart-flatbed me-2 small"></i>سلات الزبائن</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admingetsellercarts"><i class="fa-solid fa-truck-ramp-box me-2 small"></i>سلات التجار</a></li>
                </ul>
            </li>

            <li class="nav-title">العودة للمتجر</li>
            <li class="nav-item">
                <a class="nav-link text-warning fw-bold" href="/">
                    <i class="fa-solid fa-shop me-2"></i> واجهة المتجر الرئيسية
                </a>
            </li>
        </ul>
        <a href="/" class="sidebar-toggler" type="button"></a>
    </div>

    <div class="wrapper d-flex flex-column min-vh-100 bg-light">
        <header class="header header-sticky mb-4" style="background: #ffffff; border-bottom: 2px solid var(--bs-border-subtle);">
            <div class="container-fluid d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <button class="header-toggler px-md-0" type="button"
                        onclick="coreui.Sidebar.getInstance(document.querySelector('#sidebar')).toggle()">
                        <i class="fa-solid fa-bars fa-lg text-dark"></i>
                    </button>
                    <a class="btn btn-sm btn-outline-warning text-dark fw-bold" href="/">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> تصفح المتجر
                    </a>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-warning text-dark fw-bold px-3 py-2">
                        <i class="fa-solid fa-shield-halved me-1"></i> مدير النظام
                    </span>
                    <a class="btn btn-sm btn-outline-danger fw-semibold" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('admin-logout-form').submit();">
                        <i class="fa-solid fa-power-off me-1"></i> خروج
                    </a>
                    <form id="admin-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                        @csrf
                    </form>
                </div>
            </div>
        </header>

        <div class="body flex-grow-1 px-3">
            <div class="container-fluid">
                @if (session('success'))
                    <div class="alert alert-beer alert-beer-success alert-dismissible fade show mb-3" role="alert">
                        <i class="fa-solid fa-circle-check fs-5"></i>
                        <div>{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert alert-beer alert-beer-danger alert-dismissible fade show mb-3" role="alert">
                        <i class="fa-solid fa-triangle-exclamation fs-5"></i>
                        <div>{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('admincontent')
            </div>
        </div>

        <footer class="footer mt-auto py-3 bg-white border-top">
            <div class="container-fluid d-flex justify-content-between text-muted small">
                <span>&copy; {{ date('Y') }} BeerShop Admin Control Panel</span>
                <span>نظام إدارة العمليات الحرفية</span>
            </div>
        </footer>
    </div>

    <!-- CoreUI and necessary plugins-->
    <script src="{{ asset('dashb/vendors/@coreui/coreui/js/coreui.bundle.min.js') }}"></script>
    <script src="{{ asset('dashb/vendors/simplebar/js/simplebar.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-1.11.3.min.js') }}"></script>
    <script src="{{ asset('js/jquery.dataTables.min.js') }}"></script>

    @stack('scripts')
</body>
</html>
