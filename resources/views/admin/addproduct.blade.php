@extends('admin.layouts.adminmaster')
@section('admincontent')

<div class="container" style="display: block">
    <p class="mainp"><span style="color: cadetblue">Add</span> Product</p>
    <form action="/adminstoreproduct" method="POST" enctype="multipart/form-data">
        @csrf
        <div>
            <label class="namelabel" for="name">Product Name:</label>
            <input class="nameinput" type="text" id="name" name="name" required placeholder="enter name">
            <span class="text-danger">
                @error('name')
                   {{$message}}
                @enderror
             </span>
        </div>
        <div>
            <label class="pricelabel" for="price">Price:</label>
            <input class="priceinput" type="text" id="price" name="price" required >
            <span class="text-danger">
                @error('price')
                   {{$message}}
                @enderror
             </span>
            <label class="quantitylabel" for="quantity">Quantity:</label>
            <input class="quantityinput" type="text" id="quantity" name="quantity" required >
            <span class="text-danger">
                @error('quantity')
                   {{$message}}
                @enderror
             </span>
        </div>
        <div>
            <label class="descriptionlabel" for="description">Description:</label>
            <textarea class="descriptioninput" type="text" id="description" name="description" required placeholder="description"></textarea>
            <span class="text-danger">
                @error('description')
                   {{$message}}
                @enderror
             </span>
        </div>
        <div>
        <div>
            <label class="catselectlabel">Category:</label>
            <select class="catselect" name="category" id="">
                @foreach ($allcategories as $item)
                <option class="catopt" value="{{$item->id}}">{{$item->name}}</option>
                @endforeach
            </select>
            <span class="text-danger">
                @error('category')
                   {{$message}}
                @enderror
             </span>
            <label class="secondcatselectlabel">Secondcat:</label>
            <select class="secondcatselect" name="secondcategory" id="">
                @foreach ($allsecondcategories as $item)
                <option class="secondcatopt" value="{{$item->id}}">{{$item->name}}</option>
                @endforeach
            </select>
            <span class="text-danger">
                @error('secondcategory')
                   {{$message}}
                @enderror
             </span>
        </div>
        <div class="imgsdiv">
            <label class="imgpathlabel" for="imgpath">imgpath:</label>
            <input class="imgpathinput" type="file" id="imgpath" name="imgpath" required >
            <span class="text-danger">
                @error('imgpath')
                   {{$message}}
                @enderror
            </span>
            <div class="imgdiv">
            <label class="imgspathlabel" for="imgpath">img1:</label>
            <input class="imgspathinput" type="file" id="imgpathone" name="imgpathone" required >
            </div>
            <div class="imgdiv">
                <label class="imgspathlabel" for="imgpath">img2:</label>
                <input class="imgspathinput" type="file" id="imgpathtwo" name="imgpathtwo">
            </div>
            <div class="imgdiv">
            <label class="imgspathlabel" for="imgpath">img3:</label>
            <input class="imgspathinput" type="file" id="imgpaththree" name="imgpaththree">
            </div>
        </div>
        <div>
            <button type="submit">Add Product</button>
        </div>
    </form>
</div>









@endsection
