@extends('seller.layouts.sellermaster')
@section('sellercontent')
    <div class="container sellerorders mt-100" style="position: relative; min-height:100vh;">
        <table {{ $currentPage = 'showorders-page' }}>
            <thead {{$count = ($orderparts->currentPage() - 1) * $orderparts->perPage() + 1;}}>
                <tr>
                    <th>#</th>
                    <th>name</th>
                    <th>email</th>
                    <th>address</th>
                    <th>phone</th>
                    <th>note</th>
                    <th>ordertotal</th>
                    <th>status</th>
                    <th>Action</th>
                    <th>prod</th>
                </tr>
            </thead>
            <tbody>
                @if (count($orderparts) === 0)
                <tr>
                    <td colspan="9" style="font-size: 14px">
                        {{ __('string.myorder9') }}
                    </td>
                </tr>
                @else
                @foreach ($orderparts as $item)
                <tr>
                    <td>#{{ $count }}</td>
                    <td>{{$item->order->name}}</td>
                    <td>{{$item->order->email}}</td>
                    <td>{{$item->order->address}}</td>
                    <td>{{$item->order->phone}}</td>
                    <td>{{$item->order->note}}</td>
                    <td>{{$item->total}}</td>
                    <td>
                        @if ($item->status == 0)
                        <p>in seller confirmed</p>
                        @endif
                        @if ($item->status == 1)
                        <p>with diliverd corporation</p>
                        @endif
                        @if ($item->status == 2)
                        <p>in user receive confirmed</p>
                        @endif
                        @if ($item->status == 3)
                        <p>finishedorder</p>
                        @endif
                    </td>
                    <td>
                        @if ($item->status == 0)
                            <a href="/sellerconfirm/{{$item->id}}">confirm-order</a>
                        @endif
                    </td>
                    <td>
                        @foreach ($orderdetails as $value)
                            @if ($item->id == $value->orderpart_id)
                            <p style="font-size: 12px; padding: 10px 20px; background-color:bisque;">
                                N:{{ $value->product->name }}, Q:{{ $value->quantity }}, P:{{ $value->price }}
                            </p>
                            @endif
                        @endforeach
                    </td>
                </tr {{ $count++ }}>
                @endforeach
                @endif
            </tbody>
        </table>
    </div>
    <div class="links">
        {{ $orderparts->links() }}
    </div>
@endsection
