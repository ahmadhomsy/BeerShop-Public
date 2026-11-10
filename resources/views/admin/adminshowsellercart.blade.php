@extends('admin.layouts.adminmaster')
@section('admincontent')
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background-color: #f2f2f2;
        }

        table th,
        table td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
    </style>

    <table id="myTable" style="  width: 600px;">
        <thead {{ $count = ($sellers->currentPage() - 1) * $sellers->perPage() + 1 }}>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>quantity</th>
                <th>Products</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sellers as $item)
                <tr>
                    <td>#{{ $count }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->quantity }}</td>
                        <td>
                            @foreach ($carts as $value)
                                @if ($value->user_id == $item->id)
                                    <p style="margin-bottom: 2px; font-size: 12px; padding: 10px 20px; background-color:bisque;">
                                        <strong>product:</strong> {{ $value->product->name }},  <strong>q:</strong>{{ $value->quantity }}
                                    </p>
                                @endif
                            @endforeach
                        </td>
                </tr {{ $count++ }}>
            @endforeach
        </tbody>
    </table>
    <div class="links">
        {{ $sellers->links() }}
    </div>











    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
@endsection
