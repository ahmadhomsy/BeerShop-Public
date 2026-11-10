@extends('admin.layouts.adminmaster')
@section('admincontent')
    <div class="container">
        <table>
            <thead {{ $count = ($orderparts->currentPage() - 1) * $orderparts->perPage() + 1 }}>
                <tr>
                    <th>#</th>
                    <th>reciverName</th>
                    <th>reciverEmail</th>
                    <th>reciverAddress</th>
                    <th>reciverPhone</th>
                    <th>reciverNote</th>
                    <th>ordertotal</th>
                    <th>status</th>
                    <th>Actions</th>
                    <th>order details</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orderparts as $item)
                    <tr>
                        <td>#{{ $count }}</td>
                        <td>{{ $item->order->name }}</td>
                        <td>{{ $item->order->email }}</td>
                        <td>{{ $item->order->address }}</td>
                        <td>{{ $item->order->phone }}</td>
                        <td>{{ $item->order->note }}</td>
                        <td>{{ $item->total }}</td>
                        <td>
                            <p>
                                @if ($item->status == 0)
                                    with seller
                                @endif
                                @if ($item->status == 1)
                                    with driver corporation
                                @endif
                                @if ($item->status == 2)
                                    with costemer
                                @endif
                                @if ($item->status == 3)
                                    orderfinished
                                @endif
                            </p>
                        </td>
                        <td>
                            @if ($item->status == 0)
                                <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;"
                                    href="/sconfirm/{{ $item->id }}">seller-confirm</a>
                            @endif
                            @if ($item->status == 1)
                                {{-- <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;" href="/driverconfirm/{{$item->id}}">confirm-driver-order</a> --}}
                            @endif
                            @if ($item->status == 2)
                                {{-- <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;" href="/costemerconfirm/{{$item->id}}">confirm-costemer-order</a> --}}
                            @endif
                            @if ($item->status == 3)
                                {{-- <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;" href="/costemerconfirm/{{$item->id}}">finishorder</a> --}}
                            @endif
                        </td>
                        <td>
                            @foreach ($orderdetails as $value)
                                @if ($item->id == $value->orderpart_id)
                                    <p style="  margin-bottom: 2px; font-size: 12px; padding: 10px 20px; background-color:bisque;">
                                        product: {{ $value->product->name }}, q:{{ $value->quantity }},
                                        p:{{ $value->price }}
                                    </p>
                                @endif
                            @endforeach
                        </td>
                    </tr {{ $count++ }}>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="links">
        {{ $orderparts->links() }}
    </div>

    <style>
        th,
        td {
            padding-left: 10px;
            border-right: 1px solid #eee;
            border-left: 1px solid #eee;
        }

        tr {
            border: 2px solid #898989;
        }

        th:first-of-type,
        td:first-of-type {
            padding-left: 0;
        }
    </style>
@endsection
