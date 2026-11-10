@extends('seller.layouts.sellermaster')
@section('sellercontent')
    <div class="container cardscont mt-80" {{ $currentPage = 'main-page' }}>
        <div class="card f">
            <div class="card-body">
                <div class="fs-4 fw-semibold">{{ $productssellerinuserscarts }} of {{ $sellerproductsquantity }}</div>
                <div class="para">your products in costumers carts</div>
                <div class="progress progress-thin my-2">
                    <div class="progress-bar bg-info" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0"
                        aria-valuemax="100"></div>
                </div><small class="text-medium-emphasis">"more products more money"</small>
            </div>
        </div>
        <div class="card s">
            <div class="card-body">
                <div class="fs-4 fw-semibold">${{$ernings}}</div>
                <div class="para">total earnings</div>
                <div class="progress progress-thin my-2">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: 25%" aria-valuenow="25"
                        aria-valuemin="0" aria-valuemax="100"></div>
                </div><small class="text-medium-emphasis">This net profit is subtracted from the company's
                    receivables</small>
            </div>
        </div>
        <div class="chart">
            <canvas class="myChart" id="myChart"></canvas>
        </div>
    </div>

    <div class="tablesection sellerdash">
        <div class="container">
            <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" style="width: 50px;">#</th>
                            <th scope="col" style="width: 70px;">{{ __('string.cart1') }}</th>
                            <th scope="col">{{ __('string.cart2') }}</th>
                            <th scope="col">{{ __('string.cart3') }}</th>
                            <th scope="col">{{ session('locale') === 'en' ? 'Status' : 'الحالة' }}</th>
                            <th scope="col">{{ session('locale') === 'en' ? 'Added Date' : 'تاريخ الإضافة' }}</th>
                            <th scope="col" class="text-center">{{ session('locale') === 'en' ? 'Actions' : 'إجراءات' }}</th>
                        </tr>
                    </thead>
                    <tbody @php $count = ($sellerpaginateproducts->currentPage() - 1) * $sellerpaginateproducts->perPage() + 1; @endphp>
                        @foreach ($sellerpaginateproducts as $item)
                            <tr>
                                <th scope="row" class="text-muted">#{{ $count }}</th>
                                <td>
                                    <img src="{{ asset(ltrim($item->imgpath, '/')) }}" alt="{{ $item->name }}" style="width: 50px; height: 50px; object-fit: cover; border-radius: var(--bs-radius-sm);">
                                </td>
                                <td class="fw-bold">{{ $item->name }}</td>
                                <td class="text-warning-emphasis fw-bold" style="color: var(--bs-primary-beer) !important;">${{ number_format($item->price, 2) }}</td>
                                <td>
                                    <span class="badge-stock">
                                        <i class="fa-solid fa-circle-check"></i>
                                        {{ session('locale') === 'en' ? 'Active' : 'نشط' }}
                                    </span>
                                </td>
                                <td class="text-muted small">{{ $item->created_at->format('Y-m-d') }}</td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-outline-primary me-1" href="/editproduct/{{ $item->id }}" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a class="btn btn-sm btn-outline-danger" href="/deleteproduct/{{ $item->id }}" onclick="return confirm('{{ session('locale') === 'en' ? 'Are you sure you want to delete this product?' : 'هل أنت متأكد من حذف المنتج؟' }}')" title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </td>
                            </tr>
                            @php $count++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="links">
        {{ $sellerpaginateproducts->links() }}
    </div>



    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const ctx = document.getElementById('myChart');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    @foreach ($catid as $item)
                        @foreach ($categories as $cat)
                            @if ($cat->id == $item)
                                '{{ $cat->name }}',
                            @endif
                        @endforeach
                    @endforeach
                ],
                datasets: [{
                    label: [
                        @foreach ($catproductsnum as $item)
                            {{ $item }},
                        @endforeach
                    ],

                    data: [
                        @foreach ($catproductsnum as $item)
                            {{ $item }},
                        @endforeach
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                datasets: [{
                    //backgroundColor: '#eee',
                    // Other dataset properties...
                }]
            }
        });
    </script>
@endsection
