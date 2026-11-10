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
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>first registar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->email }}</td>
                    <td>{{ $item->created_at }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>












    <script>
        $(document).ready(function() {
            $('#myTable').DataTable();
        });
    </script>
@endsection
