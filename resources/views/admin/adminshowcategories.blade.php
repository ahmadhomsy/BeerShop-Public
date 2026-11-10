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
        <thead {{ $count = ($categories->currentPage() - 1) * $categories->perPage() + 1 }}>
            <tr>
                <th>#</th>
                <th>img</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($categories as $item)
                <tr>
                    <td>#{{ $count }}</td>
                    <td><img src="{{$item->imgpath}}" alt="imgpath" style="max-width: 70px; max-height:70px;min-width: 70px; min-height:70px;"></td>
                    <td>{{ $item->name }}</td>
                    <td>
                        <a class="btn" href="/admineditcat/{{$item->id}}">edit</a>
                        <a class="btn" href="/admindeletecat/{{$item->id}}">delete</a>
                    </td>
                </tr {{ $count++ }}>
            @endforeach
        </tbody>
    </table>
    <div class="links">
        {{ $categories->links() }}
    </div>











    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
@endsection
