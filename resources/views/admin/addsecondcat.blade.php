@extends('admin.layouts.adminmaster')
@section('admincontent')
<style>
    form {
        position: relative;
        width: 400px;
        background-color: #777;
    }
    label {
        padding: 7px;
  color: black;
  font-size: 18px;
  font-weight: 500;
    }
    .scat {
        position: relative;
        width: 300px;
        height: 35px;
        margin-left: 72px;
        margin-bottom: 10px
    }
    .img {
        position: relative;
        width: 200px;
        height: 40px;
        display: block;
    }
    .select {
        position: relative;
        width: 200px;
        height: 20px;
        display: block;
    }

</style>
    <form action="/adminstorescat" method="POST" enctype="multipart/form-data">
        @csrf
        <label for="scat"> category name:</label>
        <input class="scat" id="scat" type="text" name="secondcategory" required>
        <input class="img" type="file" name="imgpath" required>
        <select class="select" name="category" id="">
            @foreach ($categories as $item)
            <option value="{{$item->id}}">{{$item->name}}</option>
            @endforeach
        </select>
        <button type="submit">add second cat</button>
    </form>
@endsection
