<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use \App\Models\Category;
use \App\Models\SecondCategory;
use \App\Models\Product;
use \App\Models\Cart;

class CategoryController extends Controller
{
    public function ShowCategory($catid = null, $seconcatid = null) {
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

      if($catid != null && $seconcatid == null) {
        $categories = Category::where('id', $catid)->get();
        $products = Product::where('category_id', $catid)->get();

        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();

    }
    if($catid != null && $seconcatid != null) {
        $categories = Category::where('id', $catid)->get();

        $prod1 = Product::where('second_category_id', $seconcatid )->get();
        $prod2 = Product::where('category_id', $catid)->get();
        $products = $prod1->concat($prod2); 

        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
      }
    if($catid == null && $seconcatid == null) {
        $categories = '';
        $products = Product::all();

        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
      }
      return view('/products', ['categories'=>$categories, 'products'=>$products,
      'allcategories'=>$allcategories, 'allsecondcategories'=>$allsecondcategories,
      'cartcount'=>$cartcount ]);
    }


    //Admin functions
    public function AdminShowCategories() {
        $categories = Category::paginate(10);

        return view('admin.adminshowcategories', ['categories'=>$categories]);
    }
    public function AdminShowSecondCategories() {

        $scategories = SecondCategory::with('category')->paginate(10);
        return view('admin.adminshowsecondcategories', ['scategories'=>$scategories]);
    }

    public function AdminDeleteCategory($catid=null, $scatid=null) {
        if ($catid != null && $scatid !=null) {
            SecondCategory::where('id', $scatid)->delete();
            return redirect('/adminshowsecondcategories');
        }else {
            Category::where('id', $catid)->delete();
            return redirect('/adminshowcategories');
        }
    }

    public function AdminAddCategory() {
            return view('/admin.addcat');
        }
    public function AdminAddSecondCategory() {
        $categories = Category::all();
            return view('/admin.addsecondcat', ['categories'=>$categories]);
        }

    public function AdminStoreCategory(Request $request) {
        $request->validate([
            'category' => ['required', 'string'],
            'imgpath' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);
            $newcat = new Category();
            $newcat->name = $request->category;
                $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
            $newcat->imgpath = $path;
            $newcat ->save();

            return redirect('/adminshowcategories');
        }
    public function AdminStoreSecondCategory(Request $request) {
        $request->validate([
            'secondcategory' => ['required', 'string'],
            'category' => 'required | string',
            'imgpath' => ' image | mimes:jpeg,png,jpg,gif | max:2048',
        ]);
            $newscat = new SecondCategory();
            $newscat->name = $request->secondcategory;
            $newscat->category_id = $request->category;
                $path = $request->imgpath->move('uploads', Str::uuid()->toString() . '-' . $request->imgpath->getClientOriginalName());
            $newscat->imgpath = $path;
            $newscat ->save();

            return redirect('/adminshowsecondcategories');
        }
}
