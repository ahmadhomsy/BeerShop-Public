<!DOCTYPE html>
<html lang="{{ session()->get('locale') ?? 'ar' }}" dir="{{ session()->get('locale') === 'en' ? 'ltr' : 'rtl' }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beershop - {{ session('locale') === 'en' ? 'Seller Hub' : 'لوحة التاجر' }}</title>
    <!-- Render All Elements Normally -->
    <link rel="stylesheet" href="{{ asset('../css/normalize.css') }}">
    <link rel="stylesheet" href="{{ asset('../css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('../css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('../css/design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('../css/seller.css') }}">
    <link rel="stylesheet" href="{{ asset('css/jquery.dataTables.min.css') }}">
</head>

<body>
    <div class="top-bar">
        <div class="left-box">
            <div class="list">
                <i onclick=ff() class="fa-solid fa-bars"></i>
            </div>
            <a class="logo" href="/">
                <img src="{{ asset('../images/kkkk.jpg') }}" alt="logo">
                <p>beer</p>
            </a>
        </div>
        <div class="right-box">
            <a class="profilecont" class="one" href="/signin">
                <div class="profile">
                    <span>
                        @guest
                        @else
                            <li class="third-li">
                                <a id="navbarDropdown" href="#" role="button" aria-haspopup="true"
                                    aria-expanded="false" v-pre>
                                    <i class="fa-regular fa-user"></i> {{ Auth::user()->name }}
                                </a>
                            </li>
                        @endguest
                    </span>
                </div>
            </a>
        </div>
    </div>
    <nav>
        <div class="left-list">
            <p>All Gategories</p>
            <ul class="menu">
                    @if($currentPage === 'main-page')
                    <li><a class="choice highlighted" href="{{route('showsellerproduct')}}">-main</a>
                    </li>
                    @else
                    <li><a class="choice" href="{{route('showsellerproduct')}}" >-main</a>
                    </li>
                    @endif
                    @if($currentPage === 'addproduct-page')
                    <li><a class="choice highlighted" href="{{route('addproduct')}}" class="{{$currentPage === 'addproduct-page' ? 'highlighted' : 'nothighlighted'; }}">-add products</a>
                    </li>
                    @else
                    <li><a class="choice" href="{{route('addproduct')}}" class="{{$currentPage === 'addproduct-page' ? 'highlighted' : 'nothighlighted'; }}">-add products</a>
                    </li>
                    @endif
                    @if($currentPage === 'showorders-page')
                    <li><a class="choice highlighted" href="{{route('showorders')}}" class="{{$currentPage === 'showorders-page' ? 'highlighted' : 'nothighlighted'; }}">-orders</a>
                    </li>
                    @else
                    <li><a class="choice" href="{{route('showorders')}}" class="{{$currentPage === 'showorders-page' ? 'highlighted' : 'nothighlighted'; }}">-orders</a>
                    </li>
                    @endif
                    <li><a class="choice" href="#">-costemers reviews</a>
                    </li>
                    <li><a class="choice" href="#">-contact us</a>
                    </li>
                @auth
                    @if (Route::has('login'))
                        <li class="four-li">
                            <a class="choice" href="/">>return back</a>
                        </li>
                        <li class="four-li">
                            <a class="choice"
                                href="{{ route('logout') }}"onclick="event.preventDefault();document.getElementById('logout-form').submit();">
                                {{ __('Logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </li>
                    @endif
                @endauth
            </ul>
        </div>
    </nav>

{{-- </div> --}}
    @yield('sellercontent')


    <div class="loader" id="loader">
        <div class="loader-inner">
            <div class="circle"></div>
        </div>
    </div>
    <footer>
        <div class="container">
            <p class="f">{{ __('string.footer1') }}</p>
            <div class="i-cont">
                <i class="fa-brands fa-youtube"></i>
                <i class="fa-brands fa-twitter"></i>
                <i class="fa-brands fa-facebook"></i>
            </div>
            <ul>
                <li><a href="#">{{ __('string.footerlist1') }}</a></li>
                <li><a href="#">{{ __('string.footerlist2') }}</a></li>
                <li><a href="#">{{ __('string.footerlist3') }}</a></li>
                <li><a href="#">{{ __('string.footerlist4') }}</a></li>
            </ul>
            <hr>
            <p class="l">{{ __('string.footer2') }}</p>
        </div>
    </footer>
    {{-- <script src="{{ asset('../js/main.js') }}"></script> --}}
    <script src="{{ asset('../js/seller.js') }}"></script>
</body>

</html>
