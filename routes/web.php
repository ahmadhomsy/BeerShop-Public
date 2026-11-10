<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use illuminate\Support\Facades\Auth;
use \App\Models\Product;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Auth::routes();


///////////for all section
Route::get('/', [MainController::class, 'ShowMain']);

Route::post('/search', [ProductController::class, 'SearchProduct']);

Route::get('/showcart', [MainController::class, 'ShowCart'])->middleware('auth');

Route::get('/addcart/{prodid}', [MainController::class, 'AddCart'])->middleware('auth');
Route::post('/addcartq', [MainController::class, 'AddCartQ'])->middleware('auth');

Route::get('/deleteonecart/{prodid}', [MainController::class, 'deleteOneCart'])->middleware('auth');
Route::get('/deletecart/{prodid}', [MainController::class, 'deleteCart'])->middleware('auth');
Route::get('/clearallcart', [MainController::class, 'ClearAllCart'])->middleware('auth');

Route::get('/products/{catid?}', [CategoryController::class, 'ShowCategory']);
Route::get('/products/{catid}/{secondcatid}', [CategoryController::class, 'ShowCategory']);

Route::get('/showproduct/{prodid}', [ProductController::class, 'ShowProduct']);

Route::get('/checkout', [MainController::class, 'CheckOut'])->middleware('auth');
Route::post('/storeorder', [MainController::class, 'StoreOrder'])->middleware('auth');

Route::get('/showuserorders', [MainController::class, 'ShowUsersOrders'])->middleware('auth');
Route::get('/costemerconfirm/{orderid}', [MainController::class, 'ConfirmFinishedOrders'])->middleware('auth');



///////////seller section
Route::get('/showproducts', [ProductController::class, 'ShowSellerProduct'])->name('showsellerproduct')->middleware('auth','customsellerauth');

Route::get('/addproduct', [ProductController::class, 'AddProduct'])->name('addproduct')->middleware('auth','customsellerauth');
Route::post('/storeproduct', [ProductController::class, 'StoreProduct'])->middleware('auth','customsellerauth');

Route::get('/editproduct/{prodid}', [ProductController::class, 'EditProduct'])->middleware('auth','customsellerauth');
Route::post('/storeeditproduct', [ProductController::class, 'StoreEditProduct'])->middleware('auth','customsellerauth');
Route::get('/deleteproduct/{prodid}', [ProductController::class, 'DeleteProduct'])->middleware('auth','customsellerauth');

Route::get('/showorders', [MainController::class, 'ShowOrders'])->name('showorders')->middleware('auth','customsellerauth');

Route::get('/sellerconfirm/{orderpartid}', [MainController::class, 'SellerConfirm'])->middleware('auth','customsellerauth');




///////////admin section
Route::get('/showdash', [MainController::class,'ShowDash'])->middleware('auth','customadminauth')->name('showdash');

Route::get('/showusers/{role}', [MainController::class, 'ShowUsers'])->middleware('auth','customadminauth');
Route::get('/mainorders', [MainController::class, 'MainOrders'])->middleware('auth','customadminauth');
Route::get('/sellerorders', [MainController::class, 'SellerOrders'])->middleware('auth','customadminauth');
Route::get('/finishedorders', [MainController::class, 'FinishedOrders'])->middleware('auth','customadminauth');

Route::get('/sconfirm/{orderpartid}', [MainController::class, 'SConfirm'])->middleware('auth','customadminauth');
Route::get('/dconfirm/{orderid}', [MainController::class, 'DConfirm'])->middleware('auth','customadminauth');
Route::get('/cconfirm/{orderid}', [MainController::class, 'CConfirm'])->middleware('auth','customadminauth');

///products
Route::get('/adminshowproducts', [ProductController::class, 'AdminShowProducts'])->middleware('auth','customadminauth');
Route::get('/adminshowlastproducts', [ProductController::class, 'AdminShowLastProducts'])->middleware('auth','customadminauth');

Route::get('/admineditproduct/{prodid}', [ProductController::class, 'AdminEditProduct'])->middleware('auth','customadminauth');
Route::post('/adminstoreeditproduct', [ProductController::class, 'AdminStoreEditProduct'])->middleware('auth','customadminauth');
Route::get('/admindeleteproduct/{prodid}', [ProductController::class, 'AdminDeleteProduct'])->middleware('auth','customadminauth');

Route::get('/adminaddproduct', [ProductController::class, 'AdminAddProduct'])->middleware('auth','customadminauth');
Route::post('/adminstoreproduct', [ProductController::class, 'AdminStoreProduct'])->middleware('auth','customadminauth');

///carts
Route::get('/admingetcostemercarts', [MainController::class, 'AdminGetCostemerCarts'])->middleware('auth','customadminauth');
Route::get('/admingetsellercarts', [MainController::class, 'AdminGetSellerCarts'])->middleware('auth','customadminauth');

///category
Route::get('/adminshowcategories', [CategoryController::class, 'AdminShowCategories'])->middleware('auth','customadminauth');
Route::get('/adminshowsecondcategories', [CategoryController::class, 'AdminShowSecondCategories'])->middleware('auth','customadminauth');

//Route::get('/admineditcat/{prodid}', [MainController::class, 'AdminEditCat'])->middleware('auth','customadminauth');
//Route::post('/adminstoreeditcat', [MainController::class, 'AdminStoreEditCat'])->middleware('auth','customadminauth');

Route::get('/admindeletecat/{catid}/{scatid?}', [CategoryController::class, 'AdminDeleteCategory'])->middleware('auth','customadminauth');

Route::get('/adminaddcat', [CategoryController::class, 'AdminAddCategory'])->middleware('auth','customadminauth');
Route::get('/adminaddscat', [CategoryController::class, 'AdminAddSecondCategory'])->middleware('auth','customadminauth');

Route::post('/adminstorecat', [CategoryController::class, 'AdminStoreCategory'])->middleware('auth','customadminauth');
Route::post('/adminstorescat', [CategoryController::class, 'AdminStoreSecondCategory'])->middleware('auth','customadminauth');



Route::get('/home', [App\Http\Controllers\HomeController::class, 'index']);

Route::post('/lang/{locale}', function($locale) {
    session()->put('locale', $locale);
    return redirect()->back();
})->name('changelang');

