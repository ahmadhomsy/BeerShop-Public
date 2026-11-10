@extends('admin.layouts.adminmaster')
@section('admincontent')
    <div class="container">
        <table>
            <thead {{ $count = ($orders->currentPage() - 1) * $orders->perPage() + 1 }}>
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
                    <th>order parts</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr>
                        <td>#{{ $count }}</td>
                        <td>{{ $order->name }}</td>
                        <td>{{ $order->email }}</td>
                        <td>{{ $order->address }}</td>
                        <td>{{ $order->phone }}</td>
                        <td>{{ $order->note }}</td>
                        <td>{{ $order->total }}</td>
                        <td>
                            <p style="font-size: 13px; padding: 4px 10px; background-color:grey;">
                                @if ($order->status == 0)
                                    with seller
                                @endif
                                @if ($order->status == 1)
                                    with driver corporation
                                @endif
                                @if ($order->status == 2)
                                    with costemer
                                @endif
                                @if ($order->status == 3)
                                    orderfinished
                                @endif
                            </p>
                        </td>
                        <td>
                            @if ($order->status == 0)
                                {{-- <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;" href="/ /{{ $order->id }}">confirm-seller-order</a> --}}
                            @endif
                            @if ($order->status == 1)
                                <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;"
                                    href="/dconfirm/{{ $order->id }}">driver-confirm</a>
                            @endif
                            @if ($order->status == 2)
                                <a style="color:white; font-size: 14px; padding: 4px 10px; background-color:darkblue; border-radius:5px;text-decoration:none;"
                                    href="/cconfirm/{{ $order->id }}">costemer-confirm</a>
                            @endif
                            @if ($order->status == 3)
                            @endif
                        </td>
                        <td>
                            @foreach ($orderparts as $value)
                                @if ($order->id == $value->order_id)
                                    <p style="  font-size: 14px; padding: 5px 12px; background-color:bisque;">
                                        N:{{ $value->seller->name }}, Q:{{ $value->quantity }}, P:{{ $value->total }},
                                        S:@if ($value->status == 0)
                                            with seller
                                        @endif
                                        @if ($value->status == 1)
                                            with driver
                                        @endif
                                        @if ($value->status == 2)
                                            with costemer
                                        @endif
                                        @if ($value->status == 3)
                                            orderfinished
                                        @endif
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
        {{ $orders->links() }}
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
