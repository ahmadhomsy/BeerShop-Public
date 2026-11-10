@extends('admin.layouts.adminmaster')
@section('admincontent')


        </header>
        <div class="body flex-grow-1 px-3">
            <div class="container-lg">
                <div class="row">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card mb-4 text-white bg-primary">
                            <div class="card-body pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fs-4 fw-semibold">{{$totalpriceordersdrive}}$ <span class="fs-6 fw-normal">(
                                            <svg class="icon">
                                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-arrow-bottom">
                                                </use>
                                            </svg>)</span></div>
                                    <div>Ernings</div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-transparent text-white p-0" type="button"
                                        data-coreui-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <svg class="icon">
                                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-options"></use>
                                        </svg>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item"
                                            href="#">Action</a><a class="dropdown-item" href="#">Another
                                            action</a><a class="dropdown-item" href="#">Something else here</a>
                                    </div>
                                </div>
                            </div>
                            <div class="c-chart-wrapper mt-3 mx-3" style="height:70px;">
                                <canvas class="chart" id="card-chart1" height="70"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                    <div class="col-sm-6 col-lg-3">
                        <div class="card mb-4 text-white bg-info">
                            <div class="card-body pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fs-4 fw-semibold">{{$completedorders->count()}} <span class="fs-6 fw-normal">({{$completeper}}%
                                            <svg class="icon">
                                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-arrow-top">
                                                </use>
                                            </svg>)</span></div>
                                    <div>completed orders</div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-transparent text-white p-0" type="button"
                                        data-coreui-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <svg class="icon">
                                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-options"></use>
                                        </svg>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item"
                                            href="#">Action</a><a class="dropdown-item" href="#">Another
                                            action</a><a class="dropdown-item" href="#">Something else here</a>
                                    </div>
                                </div>
                            </div>
                            <div class="c-chart-wrapper mt-3 mx-3" style="height:70px;">
                                <canvas class="chart" id="card-chart2" height="70"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                    <div class="col-sm-6 col-lg-3">
                        <div class="card mb-4 text-white bg-warning">
                            <div class="card-body pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fs-4 fw-semibold">{{$productsinusercarts}} <span class="fs-6 fw-normal">({{$bcartsperc}},{{$acartsperc}}%)
                                            <svg class="icon">
                                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-arrow-top">
                                                </use>
                                            </svg></span></div>
                                    <div>Products in users carts</div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-transparent text-white p-0" type="button"
                                        data-coreui-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <svg class="icon">
                                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-options"></use>
                                        </svg>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item"
                                            href="#">Action</a><a class="dropdown-item" href="#">Another
                                            action</a><a class="dropdown-item" href="#">Something else here</a>
                                    </div>
                                </div>
                            </div>
                            <div class="c-chart-wrapper mt-3" style="height:70px;">
                                <canvas class="chart" id="card-chart3" height="70"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                    <div class="col-sm-6 col-lg-3">
                        <div class="card mb-4 text-white bg-danger">
                            <div class="card-body pb-0 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fs-4 fw-semibold">44K <span class="fs-6 fw-normal">(-23.6%
                                            <svg class="icon">
                                                <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-arrow-bottom">
                                                </use>
                                            </svg>)</span></div>
                                    <div>Sessions</div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-transparent text-white p-0" type="button"
                                        data-coreui-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <svg class="icon">
                                            <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-options"></use>
                                        </svg>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item"
                                            href="#">Action</a><a class="dropdown-item" href="#">Another
                                            action</a><a class="dropdown-item" href="#">Something else here</a>
                                    </div>
                                </div>
                            </div>
                            <div class="c-chart-wrapper mt-3 mx-3" style="height:70px;">
                                <canvas class="chart" id="card-chart4" height="70"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                </div>
                <!-- /.row-->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title mb-0">Traffic</h4>
                                <div class="small text-medium-emphasis">January - July 2022</div>
                            </div>
                            <div class="btn-toolbar d-none d-md-block" role="toolbar"
                                aria-label="Toolbar with buttons">
                                <div class="btn-group btn-group-toggle mx-3" data-coreui-toggle="buttons">
                                    <input class="btn-check" id="option1" type="radio" name="options"
                                        autocomplete="off">
                                    <label class="btn btn-outline-secondary"> Day</label>
                                    <input class="btn-check" id="option2" type="radio" name="options"
                                        autocomplete="off" checked="">
                                    <label class="btn btn-outline-secondary active"> Month</label>
                                    <input class="btn-check" id="option3" type="radio" name="options"
                                        autocomplete="off">
                                    <label class="btn btn-outline-secondary"> Year</label>
                                </div>
                                <button class="btn btn-primary" type="button">
                                    <svg class="icon">
                                        <use xlink:href="vendors/@coreui/icons/svg/free.svg#cil-cloud-download"></use>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="c-chart-wrapper" style="height:300px;margin-top:40px;">
                            <canvas class="chart" id="main-chart" height="300"></canvas>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row row-cols-1 row-cols-md-5 text-center">
                            <div class="col mb-sm-2 mb-0">
                                <div class="text-medium-emphasis">Visits</div>
                                <div class="fw-semibold">29.703 Users (40%)</div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 40%"
                                        aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col mb-sm-2 mb-0">
                                <div class="text-medium-emphasis">Unique</div>
                                <div class="fw-semibold">24.093 Users (20%)</div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: 20%"
                                        aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col mb-sm-2 mb-0">
                                <div class="text-medium-emphasis">Pageviews</div>
                                <div class="fw-semibold">78.706 Views (60%)</div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 60%"
                                        aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col mb-sm-2 mb-0">
                                <div class="text-medium-emphasis">New Users</div>
                                <div class="fw-semibold">22.123 Users (80%)</div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-danger" role="progressbar" style="width: 80%"
                                        aria-valuenow="80" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="col mb-sm-2 mb-0">
                                <div class="text-medium-emphasis">Bounce Rate</div>
                                <div class="fw-semibold">40.15%</div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar" role="progressbar" style="width: 40%"
                                        aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.card.mb-4-->
                <div class="row">
                    <div class="col-sm-6 col-lg-4">
                        <div class="card mb-4" style="--cui-card-cap-bg: #3b5998">
                            <div
                                class="card-header position-relative d-flex justify-content-center align-items-center">
                                <svg class="icon icon-3xl text-white my-4">
                                    <use xlink:href="vendors/@coreui/icons/svg/brand.svg#cib-facebook-f"></use>
                                </svg>
                                <div class="chart-wrapper position-absolute top-0 start-0 w-100 h-100">
                                    <canvas id="social-box-chart-1" height="90"></canvas>
                                </div>
                            </div>
                            <div class="card-body row text-center">
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$sellers->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">sellers</div>
                                </div>
                                <div class="vr"></div>
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$costemers->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">costemers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                    <div class="col-sm-6 col-lg-4">
                        <div class="card mb-4" style="--cui-card-cap-bg: #00aced">
                            <div
                                class="card-header position-relative d-flex justify-content-center align-items-center">
                                <svg class="icon icon-3xl text-white my-4">
                                    <use xlink:href="vendors/@coreui/icons/svg/brand.svg#cib-twitter"></use>
                                </svg>
                                <div class="chart-wrapper position-absolute top-0 start-0 w-100 h-100">
                                    <canvas id="social-box-chart-2" height="90"></canvas>
                                </div>
                            </div>
                            <div class="card-body row text-center">
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$allcategories->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">categories</div>
                                </div>
                                <div class="vr"></div>
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$allproducts->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">products</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                    <div class="col-sm-6 col-lg-4">
                        <div class="card mb-4" style="--cui-card-cap-bg: #4875b4">
                            <div
                                class="card-header position-relative d-flex justify-content-center align-items-center">
                                <svg class="icon icon-3xl text-white my-4">
                                    <use xlink:href="vendors/@coreui/icons/svg/brand.svg#cib-linkedin"></use>
                                </svg>
                                <div class="chart-wrapper position-absolute top-0 start-0 w-100 h-100">
                                    <canvas id="social-box-chart-3" height="90"></canvas>
                                </div>
                            </div>
                            <div class="card-body row text-center">
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$uncompletedorders->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">uncomplete orders</div>
                                </div>
                                <div class="vr"></div>
                                <div class="col">
                                    <div class="fs-5 fw-semibold">{{$completedorders->count()}}</div>
                                    <div class="text-uppercase text-medium-emphasis small">completed orders</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                </div>
                <!-- /.row-->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header">Last Orders</div>
                            <div class="card-body">
                                <br>
                                <div class="table-responsive">
                                    <table class="table border mb-0">
                                        <thead class="table-light fw-semibold">
                                            <tr class="align-middle">
                                                <th class="text-center">
                                                    <svg class="icon">
                                                        <use
                                                            xlink:href="vendors/@coreui/icons/svg/free.svg#cil-people">
                                                        </use>
                                                    </svg>
                                                </th>
                                                <th>User</th>
                                                <th class="text-center">Email</th>
                                                <th>His Order</th>
                                                <th class="text-center">Payment Method</th>
                                                <th>Order Time</th>
                                                <th></th>
                                            </tr>
                                        </thead>







                                        <tbody>
                                            @foreach ($lastuserordered as $order)
                                            <tr class="align-middle">
                                                <td class="text-center">
                                                    <div class="avatar avatar-md"><img class="avatar-img"src="images/avatar.jpg" alt=""></div>
                                                </td>
                                                <td>
                                                    <div>{{$order->name}}</div>
                                                    <div class="small text-medium-emphasis"> Quantity: 10 | Total: ${{$order->total}}</div>
                                                </td>
                                                <td class="text-center">
                                                    {{$order->email}}
                                                </td>
                                                <td>
                                                    <div class="clearfix">
                                                        <div class="float-start">
                                                            <div class="fw-semibold">
                                                                @if ($order->status == 1)
                                                                35%
                                                                @endif
                                                                @if ($order->status == 2)
                                                                70%
                                                                @endif
                                                                @if ($order->status === 3)
                                                                100%
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="float-end"><small class="text-medium-emphasis">
                                                        @if ($order->status == 1)
                                                        in sellers confirmed
                                                        @endif
                                                        @if ($order->status == 2)
                                                            in drivers confirmed
                                                        @endif
                                                        @if ($order->status == 3)
                                                            order completed
                                                        @endif
                                                        </small></div>
                                                    </div>
                                                    <div class="progress progress-thin">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                                @if($order->status == 1)
                                                                style='width: 35%'
                                                                @endif
                                                                @if($order->status == 2)
                                                                style='width: 70%'
                                                                @endif
                                                                @if($order->status == 3)
                                                                style='width: 100%'
                                                                @endif
                                                              aria-valuenow="50" aria-valuemin="0"
                                                            aria-valuemax="100"></div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    cash
                                                </td>
                                                <td>
                                                    <div class="small text-medium-emphasis"></div>
                                                    <div class="fw-semibold">{{$order->created_at}}</div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /.col-->
                </div>
                <!-- /.row-->
            </div>
        </div>


@endsection
