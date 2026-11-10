@extends('layouts.master')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h2 fw-bold text-dark mb-0">
                <i class="fa-solid fa-receipt text-warning me-2"></i>
                {{ session('locale') === 'en' ? 'My Order History' : 'سجل طلباتي' }}
            </h1>
            <p class="text-muted small mt-1 mb-0">
                {{ session('locale') === 'en' ? 'Track your active and previous orders' : 'متابعة حالة الطلبات الحالية والمكتملة' }}
            </p>
        </div>
        <a href="/products" class="btn btn-sm btn-beer-secondary">
            <i class="fa-solid fa-bag-shopping me-1"></i>
            {{ session('locale') === 'en' ? 'Shop More' : 'تسوق المزيد' }}
        </a>
    </div>

    <!-- Orders Card & Table -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 50px;">#</th>
                        <th scope="col">{{ __('string.myorder1') }}</th>
                        <th scope="col">{{ __('string.myorder2') }}</th>
                        <th scope="col">{{ __('string.myorder3') }}</th>
                        <th scope="col">{{ __('string.myorder4') }}</th>
                        <th scope="col">{{ __('string.myorder5') }}</th>
                        <th scope="col">{{ __('string.myorder6') }}</th>
                        <th scope="col">{{ __('string.myorder7') }}</th>
                        <th scope="col">{{ __('string.myorder8') }}</th>
                    </tr>
                </thead>
                <tbody @php $count = ($userorders->currentPage() - 1) * $userorders->perPage() + 1; @endphp>
                    @if (count($userorders) === 0)
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fa-3x mb-3 text-warning opacity-50 d-block"></i>
                                <span class="fs-6 fw-bold">{{ __('string.myorder9') }}</span>
                            </td>
                        </tr>
                    @else
                        @foreach ($userorders as $item)
                            <tr>
                                <td class="fw-bold text-muted">#{{ $count }}</td>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td class="small text-muted">{{ $item->email }}</td>
                                <td class="small">{{ $item->address }}</td>
                                <td class="small font-monospace">{{ $item->phone }}</td>
                                <td class="fw-bold text-warning-emphasis" style="color: var(--bs-primary-beer) !important;">
                                    ${{ number_format($item->total, 2) }}
                                </td>
                                <td>
                                    @if ($item->status == 0)
                                        <span class="badge-order-status badge-status-pending">
                                            <i class="fa-solid fa-clock me-1"></i> With Seller
                                        </span>
                                    @elseif ($item->status == 1)
                                        <span class="badge-order-status badge-status-processing">
                                            <i class="fa-solid fa-truck me-1"></i> With Delivery Corp
                                        </span>
                                    @elseif ($item->status == 2)
                                        <span class="badge-order-status badge-status-processing">
                                            <i class="fa-solid fa-person-walking-luggage me-1"></i> With Customer
                                        </span>
                                    @elseif ($item->status == 3)
                                        <span class="badge-order-status badge-status-completed">
                                            <i class="fa-solid fa-circle-check me-1"></i> Order Finished
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->status == 2)
                                        <a href="/costemerconfirm/{{ $item->id }}" class="btn btn-sm btn-beer-primary">
                                            <i class="fa-solid fa-circle-check me-1"></i>
                                            Confirm Receipt
                                        </a>
                                    @else
                                        <span class="text-muted small">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-2" style="min-width: 220px;">
                                        @foreach ($orderps as $it)
                                            @if ($it->order_id == $item->id)
                                                <div class="p-2 rounded bg-light border">
                                                    <div class="small fw-bold text-dark mb-1">
                                                        <i class="fa-solid fa-store me-1 text-warning"></i>
                                                        Seller: {{ $it->seller->name ?? 'Merchant' }}
                                                    </div>
                                                    @foreach ($orderds as $value)
                                                        @if ($it->id == $value->orderpart_id)
                                                            <div class="small text-muted d-flex justify-content-between border-top pt-1 mt-1">
                                                                <span class="text-truncate" style="max-width: 130px;">
                                                                    {{ $value->product->name ?? 'Product' }}
                                                                </span>
                                                                <span>x{{ $value->quantity }} (${{ number_format($value->price, 2) }})</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                            @php $count++; @endphp
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
        @if ($userorders->hasPages())
            <div class="card-footer bg-white p-3 d-flex justify-content-center">
                {{ $userorders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
