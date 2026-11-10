@extends('seller.layouts.sellermaster')
@section('sellercontent')

    <div class="container mt-100" {{$currentPage = 'editproduct-page'}}>
        <p class="mainp"><span>Edit</span> Product</p>
        <form class="editform" action="/storeeditproduct" method="POST" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="namelabel" for="name">Product Name:</label>
                <input class="nameinput" type="text" id="name" name="name" required value="{{ $product->name }}"
                    placeholder="enter name">
                <span class="text-danger">
                    @error('name')
                        {{ $message }}
                    @enderror
                </span>
                <input type="text" id="namear" name="namear" required placeholder="Arabic Name"value="{{ $product->namear }}">
            </div>
            <div>
                <label class="pricelabel" for="price">Price:</label>
                <input class="priceinput" type="text" id="price" name="price" required
                    value="{{ $product->price }}">
                <span class="text-danger">
                    @error('price')
                        {{ $message }}
                    @enderror
                </span>
                <label class="quantitylabel" for="quantity">Quantity:</label>
                <input class="quantityinput" type="text" id="quantity" name="quantity" required
                    value="{{ $product->stock['total_stock'] ?? $product->available_stock ?? $product->quantity ?? 0 }}">
                <span class="text-danger">
                    @error('quantity')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div>
                <label class="descriptionlabel" for="description">Description:</label>
                <textarea class="descriptioninput" type="text" id="description" name="description" placeholder="description">{{ $product->description }}</textarea>
                <span class="text-danger">
                    @error('description')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div>
                <label class="catselectlabel">Category:</label>
                <select class="catselect" name="category" id="">
                    @foreach ($allcategories as $item)
                        @if ($item->id == $product->category_id)
                            <option class="catopt" value="{{ $item->id }}" selected>{{ $item->name }}</option>
                        @else
                            <option class="catopt" value="{{ $item->id }}">{{ $item->name }}</option>
                        @endif
                    @endforeach
                </select>
                <span class="text-danger">
                    @error('category')
                        {{ $message }}
                    @enderror
                </span>
                <label class="secondcatselectlabel">Secondcat:</label>
                <select class="secondcatselect" name="secondcategory" id="">
                    @foreach ($allsecondcategories as $item)
                        @if ($item->id == $product->second_category_id)
                            <option class="secondcatopt" value="{{ $item->id }}" selected>{{ $item->name }}</option>
                        @else
                            <option class="secondcatopt" value="{{ $item->id }}">{{ $item->name }}</option>
                        @endif
                    @endforeach
                </select>
                <span class="text-danger">
                    @error('secondcategory')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div class="imgcont">
                <img class="lastimg" src="{{ asset($product->imgpath)}}" alt="productimg">
                <label class="imgpathlabel" for="imgpath">imgpath:</label>
                <input class="imgpathinput" type="file" id="imgpath" name="imgpath">
                <input type="hidden" name="id" value="{{ $product->id }}">
                <span class="text-danger">
                    @error('imgpath')
                        {{ $message }}
                    @enderror
                </span>
                <div class="imgdiv">
                    <label class="imgspathlabel" for="imgpath">img1:</label>
                    <input class="imgspathinput" type="file" id="imgpathone" name="imgpathone" >
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
                <button type="submit">Update Product</button>
            </div>
        </form>
    </div>
@endsection
