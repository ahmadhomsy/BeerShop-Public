<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SecondCategory;
use App\Models\Product;
use App\Models\Image;
use App\Models\User;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderPart;
use App\Models\OrderDetail;
use App\Modules\Orders\Services\OrderService;
use App\Services\Inventory\InventoryClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class MainController extends Controller
{
    protected OrderService $orderService;
    protected InventoryClient $inventoryClient;

    public function __construct(OrderService $orderService, InventoryClient $inventoryClient)
    {
        $this->orderService = $orderService;
        $this->inventoryClient = $inventoryClient;
    }

    public function ShowMain() {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $products = Product::all();
        if (auth()->user()) {
            $user_id = auth()->user()->id;
            $cartcount = Cart::where('user_id', $user_id)->count();
        }
        else{
            $cartcount = null;
        }

        $lastproducts = Product::orderBy('created_at', 'desc')->take(10)->get();
        //or $products = Product::latest()->take(10)->get();
        $sliderimage = Image::with('slider')->where('slider_id', 1)->get();


        return view('main',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories,
        'products' => $products, 'lastproducts' => $lastproducts,
        'sliderimage' => $sliderimage, 'cartcount'=>$cartcount ]);
    }

    public function ShowCart() {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $products = Product::all();
        if(auth()->user()){
            $user_id = auth()->user()->id;
            $usercart = Cart::where('user_id', $user_id)->get();
            $cartcount  = $usercart->sum(function($item) {
                return $item->quantity;
            });
        }

        $user_id = auth()->user()->id;
        $usercart = Cart::where('user_id', $user_id)->get();

        $mess = '';
        foreach ($usercart as $value) {
            if ($value->available === false) {
                $mess = $value->name . 'product unavailable';
            }
        }

        //total of products price in cart
        $total  = $usercart->sum(function($item){
            return $item->product->price * $item->quantity;
        });

        //relative products
        if($total >= 1){
        $fid = $usercart->random()->product->category_id;
        $productcat = Product::where('category_id', $fid)->get();
        $sid = $usercart->random()->product->second_category_id;
        $productsecondcat = Product::where('second_category_id', $sid)->get();
        }
        else {
            $productcat = Product::inRandomOrder()->take(10)->get();
            $productsecondcat = Product::inRandomOrder()->take(10)->get();
        }
        return view('/cart',
        ['allcategories' => $allcategories, 'allsecondcategories' => $allsecondcategories,
        'products' => $products, 'cartcount'=>$cartcount,
        'usercart'=>$usercart, 'total'=>$total,
        'productcat'=>$productcat, 'productsecondcat'=>$productsecondcat, 'mess'=>$mess]);
    }


    public function AddCart($productid) {
        $user_id = auth()->user()->id;
        $oldCart = Cart::with('product')->where('user_id', $user_id)->where("product_id", $productid)->first();
        $product = Product::find($productid);

        if (!$product) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        try {
            $stock = $this->inventoryClient->getStock((int) $productid);
            $available = $stock['available_stock'] ?? 0;
        } catch (\Throwable $e) {
            $available = 99;
        }

        if ($oldCart) {
            if ($available > $oldCart->quantity) {
                $oldCart->quantity += 1;
                $oldCart->save();
            } else {
                return redirect('/showcart')->with('error', 'Cannot add more quantity than available in stock.');
            }
        } else {
            if ($available < 1) {
                return redirect()->back()->with('error', 'Product is currently out of stock.');
            }
            $newCart = new Cart();
            $newCart->product_id = $productid;
            $newCart->user_id = $user_id;
            $newCart->quantity = 1;
            $newCart->save();
        }

        return redirect('/showcart');
    }

    public function AddCartQ(Request $request) {
        $user_id = auth()->user()->id;
        $productid = (int) $request->productid;
        $requestedQty = (int) $request->quantity;
        $oldCart = Cart::with('product')->where('user_id', $user_id)->where("product_id", $productid)->first();
        $product = Product::find($productid);

        if (!$product) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        try {
            $stock = $this->inventoryClient->getStock($productid);
            $available = $stock['available_stock'] ?? 0;
        } catch (\Throwable $e) {
            $available = 99;
        }

        $currentInCart = $oldCart ? $oldCart->quantity : 0;
        if (($requestedQty + $currentInCart) > $available) {
            return redirect('/showcart')->with('error', 'Cannot add more quantity than available in stock.');
        }

        if ($oldCart) {
            $oldCart->quantity += $requestedQty;
            $oldCart->save();
        } else {
            $newCart = new Cart();
            $newCart->product_id = $productid;
            $newCart->user_id = $user_id;
            $newCart->quantity = $requestedQty;
            $newCart->save();
        }

        return redirect('/showcart');
    }


    public function deleteOneCart($productid) {
        $user_id = auth()->user()->id;
        $oldCart = Cart::where('user_id', $user_id)->where("product_id", $productid)->first();

        if($oldCart->quantity > 1){
            $oldCart->quantity -=1;
            $oldCart->save();
        }
        else{
            $oldCart->delete();
        }

        return redirect('/showcart');
    }
    public function deleteCart($productid) {
        $user_id = auth()->user()->id;
        $oldCart = Cart::where('user_id', $user_id)->where("product_id", $productid)->delete();

        return redirect('/showcart');
    }


    public function ClearAllCart() {
        $user_id = auth()->user()->id;
        Cart::where('user_id', $user_id)->delete();

        return redirect('/showcart');
    }


    public function CheckOut() {
        $user_id = auth()->user()->id;
        $usercart = Cart::with('product')->where('user_id', $user_id)->get();
        if (count($usercart) !== 0) {
                //total of products price in cart
                $total  = $usercart->sum(function($item){
                    return $item->product->price * $item->quantity;
                });

        $allproducts = Product::all();
        foreach ($usercart as $value) {
            foreach ($allproducts as $item) {
                if($value->product_id === $item->id){
                    if($item->quantity < $value->quantity) {
                        return redirect('/showcart');
                    }
                }
            }
        }
    return view('checkout', ['usercart'=>$usercart, 'total'=>$total]);
}
else {
    // return response()->json(['message' => 'You don’t have products in cart']);
    return redirect('/showcart')->with('message', 'You don’t have products in cart');

}
    }

    public function StoreOrder(Request $request) {
        $request->validate([
            'name' => ['required', 'string'],
            'email' => 'required|email',
            'address' => 'required',
            'phone' => 'required',
        ]);

        $user = auth()->user();
        $user_id = $user->id;

        $cartProducts = Cart::with('product')->where('user_id', $user_id)->get();
        if ($cartProducts->isEmpty()) {
            return redirect('/showcart')->with('error', 'Your cart is empty.');
        }

        $items = $cartProducts->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'quantity'   => (int) $item->quantity,
            ];
        })->values()->all();

        $orderData = [
            'name'    => $request->name,
            'email'   => $request->email,
            'address' => $request->address,
            'phone'   => (string) $request->phone,
            'note'    => $request->note ?? '',
            'items'   => $items,
        ];

        try {
            $this->orderService->create($user, $orderData);

            // Clear all user cart upon successful order
            Cart::where('user_id', $user_id)->delete();

            return redirect('/')->with('success', 'Order created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Insufficient stock for one or more items.';
            return redirect('/checkout')->with('error', $msg)->withInput();
        } catch (\App\Exceptions\InventoryServiceUnavailableException $e) {
            return redirect('/checkout')->with('error', 'Inventory service temporarily unavailable. Please try again later.')->withInput();
        } catch (\Throwable $e) {
            return redirect('/checkout')->with('error', 'Failed to place order: ' . $e->getMessage())->withInput();
        }
    }


    public function ShowOrders() {
        $user_id = auth()->user()->id;
        $orderparts = OrderPart::with('order')->where('seller_id', $user_id)->paginate(5);
        $orderdetails = OrderDetail::with(['product', 'orderpart'])->get();

        return view('seller.orders', [ 'orderparts'=>$orderparts, 'orderdetails'=>$orderdetails]);
    }



    public function SellerConfirm($orderpartid) {
        $orderpart = OrderPart::find($orderpartid);

        $orders = Order::all();
        $order = null;
        foreach ($orders as $item) {
            if($orderpart->order_id === $item->id) {
                $order = Order::find($item->id);
                break;
            }
        }

        //change orderpart status
        $orderpart->status = '1';
        $orderpart->save();

        //change order status
        $allorderpartsconfirmed = OrderPart::where('order_id',$order->id)->where('status', '1')->get();
        $allorderparts = OrderPart::where('order_id',$order->id)->get();
        if($allorderparts->count() === $allorderpartsconfirmed->count()) {
            $order->status = '1';
            $order->save();
        }


        return redirect('/showorders');
    }




    public function Showdash() {


        $completedorders = Order::where('status', 3)->get();
        $uncompletedorders = Order::where('status', 1)->Orwhere('status', 2)->get();

        $carts = Cart::all();
        $productsinusercarts=0;
        foreach ($carts as $item) {
            $productsinusercarts += $item->quantity;
        }

        $products=0;
        $allproducts = Product::all();
        foreach ($allproducts as $item) {
            $products += ($item->quantity ?? 0);
        }
        if ($products > 0) {
            $cartspercnum = $productsinusercarts / $products;
            $numberString = strval($cartspercnum);
            $decimalPosition = strpos($numberString, '.');
            if ($decimalPosition !== false) {
                $bcartsperc = substr($numberString, 0, $decimalPosition);
                $acartsperc = substr($numberString, $decimalPosition + 1, 5);
            } else {
                $bcartsperc = $numberString;
                $acartsperc = '0';
            }
        } else {
            $bcartsperc = '0';
            $acartsperc = '0';
        }


        $totalpriceorders=0;
        $totalpriceordersdrive=0;
        foreach ($completedorders as $item) {
            $totalpriceorders += $item->total -15;
            $totalpriceordersdrive += 15;
        }



        $allorders = Order::all();
        $allpartorders = OrderPart::all();

        $sellers = User::where('role', 'seller')->get();
        $costemers = User::where('role', 'user')->get();



        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();

        if(count($allorders) === 0)
        {
            $completeper = 0;
        }
        else {
            $completeper = $completedorders->count() / $allorders->count();
        }


        $lastuserordered = Order::orderByDesc('created_at')->take(10)->get();

        return view('admin.dashboard', ['completedorders'=>$completedorders, 'completeper'=>$completeper,
        'uncompletedorders'=>$uncompletedorders, 'productsinusercarts'=>$productsinusercarts,
        'totalpriceorders'=>$totalpriceorders, 'totalpriceordersdrive'=>$totalpriceordersdrive,
        'bcartsperc'=>$bcartsperc, 'acartsperc'=>$acartsperc, 'sellers'=>$sellers, 'costemers'=>$costemers,
        'allcategories'=>$allcategories, 'allproducts'=>$allproducts,
        'lastuserordered'=>$lastuserordered]);
    }


    public function ShowUsers($roleid) {
        $users = collect();
        if ($roleid == 2) {
            $users = User::where('role', 'seller')->get();
        }
        if ($roleid == 3) {
            $users = User::where('role', 'user')->get();
        }
        return view('admin.users', ['users'=>$users ]);
    }

    public function MainOrders() {

        $orders = Order::paginate(10);
        $orderparts = OrderPart::with('seller')->get();
        return view('admin.orders', ['orders'=>$orders, 'orderparts'=>$orderparts ]);
    }
    public function SellerOrders() {
        $orderparts = OrderPart::with('order')->paginate(10);
        $orderdetails = OrderDetail::with('product')->get();

        return view('admin.orderparts', ['orderparts'=>$orderparts, 'orderdetails'=>$orderdetails ]);
    }
    public function FinishedOrders() {
        $orders = Order::where('status', 3)->paginate(10);
        $orderparts = OrderPart::with('seller')->get();
        $orderdetails = OrderDetail::with('product')->get();

        return view('admin.finishedorders', ['orders'=>$orders, 'orderparts'=>$orderparts, 'orderdetails'=>$orderdetails ]);
    }


    public function SConfirm($orderpartid) {
        $orderpart = OrderPart::find($orderpartid);

        $orders = Order::all();
        $order = null;
        foreach ($orders as $item) {
            if($orderpart->order_id === $item->id) {
                $order = Order::find($item->id);
                break;
            }
        }

        //change orderpart status
        $orderpart->status = '1';
        $orderpart->save();

        //change order status
        $allorderpartsconfirmed = OrderPart::where('order_id',$order->id)->where('status', '1')->get();
        $allorderparts = OrderPart::where('order_id',$order->id)->get();
        if($allorderparts->count() === $allorderpartsconfirmed->count()) {
            $order->status = '1';
            $order->save();
        }

        return redirect('/sellerorders');
    }

    public function DConfirm($orderid) {
        $order = Order::find($orderid);
        //change order status
        $order->status = '2';
        $order->save();

        //change orderpart status
        $allorderparts = OrderPart::all();
        $orderparts = OrderPart::where('order_id', $orderid)->get();
        foreach ($allorderparts as $item) {
            if($orderid == $item->order_id) {
                foreach ($orderparts as $v) {
                    $v->status = '2';
                    $v->save();
                }
            }
        }
        return redirect('/mainorders');
    }

    public function CConfirm($orderid) {
        $order = Order::find($orderid);
        //change orderpart status
        $order->status = '3';
        $order->save();

        //change orderpart status
        $allorderparts = OrderPart::all();
        $orderparts = OrderPart::where('order_id', $orderid)->get();
        foreach ($allorderparts as $item) {
            if($orderid == $item->order_id) {
                foreach ($orderparts as $v) {
                    $v->status = '3';
                    $v->save();
                }
            }
        }
        return redirect('/finishedorders');
    }


    public function AdminGetCostemerCarts() {
        $products = Product::all();
        $users = User::where('role', 'user')->paginate(10);
        $carts = collect(); // Create an empty collection to store the carts

        foreach ($users as $user) {
            $userCarts = Cart::where('user_id', $user->id)->get();
            $carts = $carts->concat($userCarts);
        }

        return view('admin.adminshowcostemercart', ['carts' => $carts, 'products' => $products,
                'users'=>$users]);
    }
    public function AdminGetSellerCarts() {
        $products = Product::all();
        $sellers = User::where('role', 'seller')->paginate(10);
        $carts = collect(); // Create an empty collection to store the carts

        foreach ($sellers as $seller) {
            $sellerCarts = Cart::where('user_id', $seller->id)->get();
            $carts = $carts->concat($sellerCarts);
        }
        return view('admin.adminshowsellercart', ['carts' => $carts, 'products' => $products,
        'sellers'=>$sellers]);
    }

    public function ShowUsersOrders() {
        $allcategories = Category::all();
        $allsecondcategories = SecondCategory::all();
        $products = Product::all();
        if(auth()->user()){
            $user_id = auth()->user()->id;
            $usercart = Cart::where('user_id', $user_id)->get();
            $cartcount  = $usercart->sum(function($item) {
                return $item->quantity;
            });
        }

        $user_id = auth()->user()->id;
        $userorders = Order::where('user_id', $user_id)->paginate(10);
        $orderps=collect();
        $orderds=collect();
        foreach ($userorders as $order) {
            $orderparts = OrderPart::with('seller')->where('order_id', $order->id)->get();
            $orderps = $orderps->merge($orderparts);
            foreach ($orderparts as $orderpart) {
                $orderdetails = OrderDetail::where('orderpart_id', $orderpart->id)->get();
                $orderds = $orderds->merge($orderdetails);
            }
        }

        return view('orders',[ 'userorders'=>$userorders,
        'allcategories'=>$allcategories, 'allsecondcategories'=>$allsecondcategories,
        'products'=>$products, 'cartcount'=>$cartcount,
        'orderps'=>$orderps, 'orderds'=>$orderds,]);
    }

    public function ConfirmFinishedOrders($orderid) {

        $order = Order::find($orderid);
        //change orderpart status
        $order->status = '3';
        $order->save();

        //change orderpart status
        $allorderparts = OrderPart::all();
        $orderparts = OrderPart::where('order_id', $orderid)->get();
        foreach ($allorderparts as $item) {
            if($orderid == $item->order_id) {
                foreach ($orderparts as $v) {
                    $v->status = '3';
                    $v->save();
                }
            }
        }
        return redirect('/showuserorders');
    }

}





