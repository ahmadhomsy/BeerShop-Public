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
                    <th>order parts</th>
                    <th>order details</th>
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
                                orderfinished
                            </p>
                        </td>
                        <td>
                            @foreach ($orderparts as $value)
                                @if ($order->id == $value->order_id)
                                    <p style="  font-size: 14px; padding: 5px 12px; background-color:bisque;">
                                        N:{{ $value->seller->name }}, Q:{{ $value->quantity }}, P:{{ $value->total }},
                                        S:orderfinished
                                    </p>
                                @endif
                            @endforeach
                        </td>
                        <td>
                            @foreach ($orderparts as $part)
                            @foreach ($orderdetails as $value)
                                @if ($part->id == $value->orderpart_id)
                                    <p style="margin-bottom: 2px;font-size: 12px;padding: 10px 20px;background-color: bisque;">
                                        product: {{ $value->product->name }}, q:{{ $value->quantity }}, p:{{ $value->price }}
                                    </p>
                                @endif
                            @endforeach
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
        th, td {
            padding-left: 10px;
            border-right: 1px solid #eee;
            border-left: 1px solid #eee;
        }
        tr {
            border: 2px solid #898989;
        }
        th:first-of-type, td:first-of-type {
            padding-left: 0;
        }
    </style>
@endsection
