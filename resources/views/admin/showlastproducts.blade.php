@extends('admin.layouts.adminmaster')

@section('admincontent')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 fw-bold mb-1"><i class="fa-solid fa-sparkles text-warning me-2"></i>أحدث المنتجات المضافة</h2>
            <p class="text-muted small mb-0">عرض أحدث المنتجات التي تمت إضافتها للمتجر مؤخراً</p>
        </div>
        <a href="/adminaddproduct" class="btn btn-sm btn-beer-primary">
            <i class="fa-solid fa-plus me-1"></i> إضافة منتج جديد
        </a>
    </div>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="myTable">
                <thead class="table-light" @php $count = ($products->currentPage() - 1) * $products->perPage() + 1; @endphp>
                    <tr>
                        <th scope="col" style="width: 50px;">#</th>
                        <th scope="col" style="width: 70px;">الصورة</th>
                        <th scope="col">اسم المنتج</th>
                        <th scope="col">السعر</th>
                        <th scope="col">الحالة</th>
                        <th scope="col">الوصف</th>
                        <th scope="col">الفئة</th>
                        <th scope="col">الفئة الفرعية</th>
                        <th scope="col">اسم التاجر</th>
                        <th scope="col" class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $item)
                        <tr>
                            <td class="text-muted fw-bold">#{{ $count }}</td>
                            <td>
                                <img src="{{ asset(ltrim($item->imgpath, '/')) }}" alt="{{ $item->name }}" style="width: 54px; height: 54px; object-fit: cover; border-radius: var(--bs-radius-sm);">
                            </td>
                            <td class="fw-bold">{{ $item->name }}</td>
                            <td class="fw-bold text-warning-emphasis" style="color: var(--bs-primary-beer) !important;">${{ number_format($item->price, 2) }}</td>
                            <td>
                                <span class="badge-stock">
                                    <i class="fa-solid fa-circle-check"></i> متوفر
                                </span>
                            </td>
                            <td class="small text-muted text-truncate" style="max-width: 160px;" title="{{ $item->description }}">{{ $item->description }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $item->category->name ?? '—' }}</span></td>
                            <td><span class="badge bg-light text-dark border">{{ $item->secondcategory->name ?? '—' }}</span></td>
                            <td class="small fw-semibold">{{ $item->user->name ?? '—' }}</td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-primary me-1" href="/admineditproduct/{{$item->id}}" title="تعديل">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-danger" href="/admindeleteproduct/{{$item->id}}" onclick="return confirm('هل أنت متأكد من حذف هذا المنتج نهائياً؟')" title="حذف">
                                    <i class="fa-solid fa-trash-can"></i>
                                </a>
                            </td>
                        </tr>
                        @php $count++; @endphp
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-footer bg-white p-3 d-flex justify-content-center">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
