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
    .cat {
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

</style>
    <form action="/adminstorecat" method="POST" enctype="multipart/form-data">
        @csrf
        <label for="cat"> category name:</label>
        <input class="cat" id="cat" type="text" name="category" required>
        <input class="img" type="file" name="imgpath" required>
        <button type="submit">add cat</button>
    </form>
@endsection
