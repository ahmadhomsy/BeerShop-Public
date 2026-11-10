<?php
namespace App\Http\Controllers\MainController;

namespace App\Http\Controllers;
use \App\Models\Category;
use \App\Models\SecondCategory;
use \App\Models\Product;
use \App\Models\Image;
use \App\Models\User;
use \App\Models\Cart;
use \App\Models\Order;
use \App\Models\OrderPart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ProductController extends Controller
{
    public function SearchProduct(Request $request) {
        if ($request->searchkey) {
            $products =  Product::where('name', 'like', '%' .$request->searchkey. '%')->get();
            // foreach($products as $s){
            //     $catid = $s->category_id;
            // }
            if ($products->isEmpty()) {
                return redirect('/');
            }
            $catid = $products->first()->category_id;

            // return redirect('/products/{$catid}', [ 'products' => $products]);
            return redirect("/products/{$catid}")->with('products', $products);
        }
        else{
            return redirect('/');
        }
    }

    public function ShowSellerProduct() {
        $categories = Category::all();
        $user_id = auth()->user()->id;
        //get seller products
        $sellerproducts = Product::where('seller_id', $user_id)->get();
        $sellerpaginateproducts = Product::where('seller_id', $user_id)->paginate(10);

        $sellerproductsquantity = 0;
        foreach($sellerproducts as $l){
            $sellerproductsquantity += $l->quantity;
        }

        //get seller products categories ids
        $arr = [];
        foreach ($sellerproducts as $value) {
            if (!in_array($value->category_id, $arr)) {
            $arr[] = $value->category_id;
            }
        }

        //get number of products for each category
        $catproductsnum = [];
        foreach ($arr as $item) {
        $count = Product::where('seller_id', $user_id)->where('category_id', $item)->get()->count();
        $catproductsnum[] = $count;
        }

        //get the ernings of seller
        $ernings=0;
        $forder = order::where('status', 3)->get();
        foreach ($forder as $item) {
            $parts = OrderPart::where('order_id', $item->id)->where('seller_id', $user_id)->get();
            foreach ($parts as $value) {
                $ernings += $value->total;
            }
        }

        //get number of products that in users cart
        $productssellerinuserscarts = 0;
        $cartproducts = Cart::all();
        foreach($sellerproducts as $product) {
            foreach($cartproducts as $cartproduct) {
                if($product->id == $cartproduct->product_id) {
                    $productssellerinuserscarts += $cartproduct->quantity;
                }
            }
        }
        return view('seller.sellerdash', ['sellerproducts' => $sellerproducts, 'catid'=>$arr,
        'categories'=>$categories, 'catproductsnum'=>$catproductsnum,
        'productssellerinuserscarts'=>$productssellerinuserscarts,
        'sellerproductsquantity'=>$sellerproductsquantity,
        'sellerpaginateproducts'=>$sellerpaginateproducts,
        'ernings'=>$ernings]);
    }


    public function AddProduct() {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        return view('seller.addproduct',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories]);
    }
    public function StoreProduct(Request $request) {
        $valdata = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:products'],
            'namear' => ['nullable', 'string', 'max:30'],
            'price' => 'required| numeric',
            'quantity' => 'nullable | integer',
            'description' => 'nullable | string | max:255',
            'category' => 'required',
            'secondcategory' => 'required',
            'imgpath' => 'required| image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathone' => 'required| image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathtwo' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpaththree' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);
         $newproduct = new Product();
         //$user_id = Auth::id()->id;
         $user_id = auth()->user()->id;
         $newproduct->seller_id = $user_id;
         $newproduct->name = $request->name;
         $newproduct->namear = $request->namear;
         $newproduct->price = $request->price;
         $newproduct->description = $request->description;
         $newproduct->category_id = $request->category;
        $newproduct->second_category_id = $request->secondcategory;

        $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
        $newproduct->imgpath = $path;
        $newproduct ->save();

        if ($request->filled('quantity')) {
            try {
                \Illuminate\Support\Facades\DB::connection('inventory_db')->table('inventory')->updateOrInsert(
                    ['product_id' => $newproduct->id],
                    ['total_stock' => (int) $request->quantity, 'reserved_stock' => 0]
                );
            } catch (\Throwable $e) {}
        }

        $img = new Image();
        $img->product_id = $newproduct->id;
        $path = $request->imgpathone->move('uploads', Str::uuid()->toString().'-'.$request->imgpathone->getClientOriginalName());
        $img->imgpath = $path;
        $img->save();
        if($request->imgpathtwo) {
            $img2 = new Image();
            $img2->product_id = $newproduct->id;
            $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
            $img2->imgpath = $path;
            $img2->save();
        }
        if($request->imgpaththree) {
            $img3 = new Image();
            $img3->product_id = $newproduct->id;
            $path = $request->imgpaththree->move('uploads', Str::uuid()->toString() . '-' . $request->imgpaththree->getClientOriginalName());
            $img3->imgpath = $path;
            $img3->save();
        }
        return redirect('/showproducts');
    }

    public function EditProduct($prodid) {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $product = Product::find($prodid);
        if ($product) {
            try {
                $inventory = app(\App\Services\Inventory\InventoryClient::class);
                $stock = $inventory->getStock((int) $prodid);
                $product->stock = $stock;
                $product->available_stock = $stock['available_stock'] ?? 0;
            } catch (\Throwable $e) {
                $product->stock = null;
                $product->available_stock = null;
            }
        }

        return view('seller.editproduct',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories,
        'product'=>$product ]);
    }

    public function StoreEditProduct(Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'namear' => ['string', 'max:30'],
            'price' => 'required| numeric',
            'quantity' => 'nullable | integer',
            'description' => 'nullable | string | max:255',
            'category' => 'required',
            'secondcategory' => 'required',
            'imgpath' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathone' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathtwo' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpaththree' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);

        $updateproduct = Product::find($request->id);
        //$user_id = Auth::id()->id;
        $user_id = auth()->user()->id;
        $updateproduct->seller_id = $user_id;
        $updateproduct->name = $request->name;
        $updateproduct->namear = $request->namear;
        $updateproduct->price = $request->price;
        $updateproduct->description = $request->description;
        $updateproduct->category_id = $request->category;
        $updateproduct->second_category_id = $request->secondcategory;
        $path='';
        if($request->has('imgpath')) {
            $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
            $updateproduct->imgpath = $path;
        }else {
            $path = Product::find($request->id)->imgpath;
            $updateproduct->imgpath = $path;
        }
        $updateproduct->save();

        if ($request->filled('quantity')) {
            try {
                \Illuminate\Support\Facades\DB::connection('inventory_db')->table('inventory')->updateOrInsert(
                    ['product_id' => $updateproduct->id],
                    ['total_stock' => (int) $request->quantity, 'reserved_stock' => 0]
                );
            } catch (\Throwable $e) {}
        }

        if($request->has('imgpathone')) {
            $img = Image::where('product_id', $request->id)->first();
            $path = $request->imgpathone->move('uploads', Str::uuid()->toString().'-'.$request->imgpathone->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
        }
        if($request->has('imgpathtwo')) {
            $img = Image::where('product_id', $request->id)->skip(1)->first();
            if($img == null){
                $img = new Image();
                $img->product_id = $request->id;
                $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
                $img->imgpath = $path;
                $img->save();
            }
            else {
            $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
            }
        }
        if($request->has('imgpaththree')) {
            $img = Image::where('product_id', $request->id)->skip(2)->first();
            if($img == null){
                $img = new Image();
                $img->product_id = $request->id;
                $path = $request->imgpaththree->move('uploads', Str::uuid()->toString().'-'.$request->imgpaththree->getClientOriginalName());
                $img->imgpath = $path;
                $img->save();
            }
            else {
            $path = $request->imgpaththree->move('uploads', Str::uuid()->toString() . '-' . $request->imgpaththree->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
            }
        }
        return redirect('/showproducts');
    }

    public function DeleteProduct($prodid) {
        Image::where('product_id', $prodid)->delete();
        Product::where('id', $prodid)->delete();
        return redirect('/showproducts');
    }

    public function ShowProduct($prodid){
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $products = Product::all();
        if(auth()->user()){
            $user_id = auth()->user()->id;
            $usercart = Cart::where('user_id', $user_id)->get();
            $cartcount=0;
            foreach($usercart as $item) {
                $cartcount += $item->quantity;
            }
        }
        else {
            $cartcount = null;
        }


        $product = Product::find($prodid);
        if ($product) {
            try {
                $inventory = app(\App\Services\Inventory\InventoryClient::class);
                $stock = $inventory->getStock((int) $prodid);
                $product->stock = $stock;
                $product->available_stock = $stock['available_stock'] ?? 0;
            } catch (\Throwable $e) {
                $product->stock = null;
                $product->available_stock = null;
            }
        }

        $productimgs = Image::where('product_id', $prodid)->get();

        //relative products
        $fid = $product->category_id;
        $productcat = Product::where('category_id', $fid)->get();
        $sid = $product->second_category_id;
        $productsecondcat = Product::where('second_category_id', $sid)->get();

        return view('product', ['allcategories'=>$allcategories, 'allsecondcategories'=>$allsecondcategories,
        'cartcount'=>$cartcount, 'products'=>$products, 'product'=>$product,
        'productcat'=>$productcat, 'productsecondcat'=>$productsecondcat,
        'productimgs'=>$productimgs]);
    }



    public function AdminEditProduct($prodid) {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $product = Product::find($prodid);
        if ($product) {
            try {
                $inventory = app(\App\Services\Inventory\InventoryClient::class);
                $stock = $inventory->getStock((int) $prodid);
                $product->stock = $stock;
                $product->available_stock = $stock['available_stock'] ?? 0;
            } catch (\Throwable $e) {
                $product->stock = null;
                $product->available_stock = null;
            }
        }

        return view('admin.editproduct',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories,
        'product'=>$product ]);
    }

    public function AdminShowProducts() {
        $products = Product::with(['user','category','secondcategory'])->paginate(15);
        return view('admin.showproducts', ['products'=>$products]);
    }
    public function AdminShowLastProducts() {
        $products = Product::with(['user','category','secondcategory'])->orderBy('created_at', 'desc')
                                    ->take(30)->paginate(10);
        return view('admin.showlastproducts', ['products'=>$products]);
    }

    public function AdminStoreEditProduct(Request $request) {
        $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'price' => 'required| numeric',
            'quantity' => 'nullable | integer',
            'description' => 'nullable | string | max:255',
            'category' => 'required',
            'secondcategory' => 'required',
            'imgpath' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathone' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathtwo' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpaththree' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);

        $updateproduct = Product::find($request->id);
        $updateproduct->name = $request->name;
        $updateproduct->price = $request->price;
        $updateproduct->description = $request->description;
        $updateproduct->category_id = $request->category;
        $updateproduct->second_category_id = $request->secondcategory;
        $path='';
        if($request->has('imgpath')) {
            $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
            $updateproduct->imgpath = $path;
        }else {
            $path = Product::find($request->id)->imgpath;
            $updateproduct->imgpath = $path;
        }
        $updateproduct->save();

        if ($request->filled('quantity')) {
            try {
                \Illuminate\Support\Facades\DB::connection('inventory_db')->table('inventory')->updateOrInsert(
                    ['product_id' => $updateproduct->id],
                    ['total_stock' => (int) $request->quantity, 'reserved_stock' => 0]
                );
            } catch (\Throwable $e) {}
        }

        if($request->has('imgpathone')) {
            $img = Image::where('product_id', $request->id)->first();
            $path = $request->imgpathone->move('uploads', Str::uuid()->toString().'-'.$request->imgpathone->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
        }
        if($request->has('imgpathtwo')) {
            $img = Image::where('product_id', $request->id)->skip(1)->first();
            if($img == null){
                $img = new Image();
                $img->product_id = $request->id;
                $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
                $img->imgpath = $path;
                $img->save();
            }
            else {
            $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
            }
        }
        if($request->has('imgpaththree')) {
            $img = Image::where('product_id', $request->id)->skip(2)->first();
            if($img == null){
                $img = new Image();
                $img->product_id = $request->id;
                $path = $request->imgpaththree->move('uploads', Str::uuid()->toString().'-'.$request->imgpaththree->getClientOriginalName());
                $img->imgpath = $path;
                $img->save();
            }
            else {
            $path = $request->imgpaththree->move('uploads', Str::uuid()->toString() . '-' . $request->imgpaththree->getClientOriginalName());
            $img->imgpath = $path;
            $img->save();
            }
        }
        return redirect('/adminshowproducts');
    }

    public function AdminDeleteProduct($prodid) {
        Image::where('product_id', $prodid)->delete();
        Product::where('id', $prodid)->delete();
        return redirect('/adminshowproducts');
    }

    public function AdminAddProduct() {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        return view('admin.addproduct',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories]);
    }
    public function AdminStoreProduct(Request $request) {
        $valdata = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:products'],
            'price' => 'required| numeric',
            'quantity' => 'nullable | integer',
            'description' => 'nullable | string | max:255',
            'category' => 'required',
            'secondcategory' => 'required',
            'imgpath' => 'required| image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathone' => 'required| image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpathtwo' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
            'imgpaththree' => 'image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);
         $newproduct = new Product();
         //$user_id = Auth::id()->id;
         $user_id = auth()->user()->id;
         $newproduct->seller_id = $user_id;
         $newproduct->name = $request->name;
         $newproduct->price = $request->price;
         $newproduct->description = $request->description;
         $newproduct->category_id = $request->category;
        $newproduct->second_category_id = $request->secondcategory;

        $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
        $newproduct->imgpath = $path;
        $newproduct ->save();

        if ($request->filled('quantity')) {
            try {
                \Illuminate\Support\Facades\DB::connection('inventory_db')->table('inventory')->updateOrInsert(
                    ['product_id' => $newproduct->id],
                    ['total_stock' => (int) $request->quantity, 'reserved_stock' => 0]
                );
            } catch (\Throwable $e) {}
        }

        $img = new Image();
        $img->product_id = $newproduct->id;
        $path = $request->imgpathone->move('uploads', Str::uuid()->toString().'-'.$request->imgpathone->getClientOriginalName());
        $img->imgpath = $path;
        $img->save();
        if($request->imgpathtwo) {
            $img2 = new Image();
            $img2->product_id = $newproduct->id;
            $path = $request->imgpathtwo->move('uploads', Str::uuid()->toString().'-'.$request->imgpathtwo->getClientOriginalName());
            $img2->imgpath = $path;
            $img2->save();
        }
        if($request->imgpaththree) {
            $img3 = new Image();
            $img3->product_id = $newproduct->id;
            $path = $request->imgpaththree->move('uploads', Str::uuid()->toString() . '-' . $request->imgpaththree->getClientOriginalName());
            $img3->imgpath = $path;
            $img3->save();
        }
        return redirect('/adminshowproducts');
    }


}
