<?php

namespace Database\Seeders;
use \App\Models\Category;
use \App\Models\SecondCategory;
use \App\Models\Product;
use \App\Models\User;
use \App\Models\Image;
use \App\Models\Slider;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Hash;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash as FacadesHash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);




        if (User::where('email', 'aa@gmail.com')->exists()) {
            return;
        }

        $users =[
            ['name' => 'abood', 'email' => 'aa@gmail.com', 'password' => Hash::make("0000"),'role'=>'admin'],
            ['name' => 'bb', 'email' => 'bb@gmail.com','password' => Hash::make("0000"),'role'=>'seller'],
            ['name' => 'yy', 'email' => 'yy@gmail.com', 'password' => Hash::make("0000"),'role'=>'seller'],
            ['name' => 'cc', 'email' => 'cc@gmail.com', 'password' => Hash::make("0000"),'role'=>'user'],
            ['name' => 'nn', 'email' => 'nn@gmail.com', 'password' => Hash::make("0000"),'role'=>'user'],
            ['name' => 'kk', 'email' => 'kk@gmail.com', 'password' => Hash::make("0000"),'role'=>'user'],
        ];
        foreach ($users as $user) {
            User::create($user);
        }



        $categories =  [
        ['name' => 'electronics','namear' => 'الكترونيات', 'imgpath' => '/images/categories/1.jpg'],
        ['name' => 'sports','namear' => 'الرياضة', 'imgpath' => '/images/categories/2.jpg'],
        ['name' => 'beauty','namear' => 'الجمال', 'imgpath' => '/images/categories/3.jpg'],
        ['name' => 'clocks','namear' => 'الساعات', 'imgpath' => '/images/categories/4.jpg'],
        ['name' => 'clothes','namear' => 'الملابس', 'imgpath' => '/images/categories/5.jpg'],
        ['name' => 'food','namear' => 'الطعام', 'imgpath' => '/images/categories/6.jpg'],
    ];
    foreach ($categories as $category) {
        Category::create($category);
    }


$scat = [
        ['name' => 'mobiles','namear' => 'الموبايلات', 'category_id' => '1'],
        ['name' => 'tv','namear' => 'التلفاز', 'category_id' => '1'],
        ['name' => 'Laptops','namear' => 'اللابتوبات', 'category_id' => '1'],
        ['name' => 'cables','namear' => 'أسلاك الشحن', 'category_id' => '1'],
        ['name' => 'powerbanks','namear' => 'مخزن طاقة', 'category_id' => '1'],
        ['name' => 'accessories','namear' => 'اكسسوارات', 'category_id' => '1'],

        ['name' => 'balls','namear' => 'الكرات', 'category_id' => '2'],
        ['name' => 'shoes','namear' => 'الاحذية', 'category_id' => '2'],
        ['name' => 'shorts','namear' => 'شورتات السباحه', 'category_id' => '2'],
        ['name' => 't-shirts','namear' => 'كنزات الرياضه', 'category_id' => '2'],
        ['name' => 'gloves','namear' => 'القفازات', 'category_id' => '2'],

        ['name' => 'mascara','namear' => 'الماسكرات', 'category_id' => '3'],
        ['name' => 'fondation','namear' => 'الفاونديشنات', 'category_id' => '3'],
        ['name' => 'hamra','namear' => 'الحمره', 'category_id' => '3'],

        ['name' => 'digital_watch','namear' => 'ساعات رقمية', 'category_id' => '4'],
        ['name' => 'classic_watch','namear' => 'ساعات كلاسيكية', 'category_id' => '4'],
        ['name' => 'smart_watch','namear' => 'ساعات ذكية', 'category_id' => '4'],
        ['name' => 'expensive_watch','namear' => 'ساعات غالية', 'category_id' => '4'],
        ['name' => 'kids_watch','namear' => 'ساعات أطفال', 'category_id' => '4'],

        ['name' => 't-shirt','namear' => 'الكنزات', 'category_id' => '5'],
        ['name' => 'pants','namear' => 'السراويل', 'category_id' => '5'],
        ['name' => 'shorts','namear' => 'الشورتات', 'category_id' => '5'],
        ['name' => 'shoes','namear' => 'الاحذيه', 'category_id' => '5'],
        ['name' => 'caps','namear' => 'القبعات', 'category_id' => '5'],
        ['name' => 'sunglass','namear' => 'نظارات شمسيه', 'category_id' => '5'],
        ['name' => 'socks','namear' => 'الجوارب', 'category_id' => '5'],

        ['name' => 'fruits','namear' => 'الفواكه', 'category_id' => '6'],
        ['name' => 'vegtables','namear' => 'الخضراوات', 'category_id' => '6'],
        ['name' => 'fastfood','namear' => 'الاكل السريع', 'category_id' => '6'],
        ['name' => 'chips','namear' => 'الشيبسات', 'category_id' => '6'],
        ['name' => 'chocolate-food','namear' => 'الشوكولاته', 'category_id' => '6'],
    ];
    foreach ($scat as $scategory) {
        SecondCategory::create($scategory);
}

$products =[['name' => 'mobilephone','namear' => 'هاتف', 'imgpath'=>'/images/products/mobile.avif', 'price' => '2200', 'category_id' => '1', 'second_category_id' => '1','seller_id'=>'2'],
        ['name' => 'smarttv','namear' => 'تلفاز', 'imgpath'=>'/images/products/smarttv.webp', 'price' => '1200', 'category_id' => '1', 'second_category_id' => '2','seller_id'=>'2'],
        ['name' => 'laptop','namear' => 'لابتوب', 'imgpath'=>'/images/products/laptop.jpg', 'price' => '4500', 'category_id' => '1', 'second_category_id' => '3','seller_id'=>'2'],
        ['name' => 'type-c-cable','namear' => 'سلك شحن تايب سي', 'imgpath'=>'/images/products/typeccable.webp', 'price' => '20', 'category_id' => '1', 'second_category_id' => '4','seller_id'=>'2'],
        ['name' => 'besus powerbank','namear' => 'مخزن طاقه بيسوس', 'imgpath'=>'/images/products/bowerbank.jpg', 'price' => '400', 'category_id' => '1', 'second_category_id' => '5','seller_id'=>'2'],
        ['name' => 'earphones','namear' => 'سماعات اذان محيطيه', 'imgpath'=>'/images/products/earphones.webp', 'price' => '50', 'category_id' => '1', 'second_category_id' => '6','seller_id'=>'2'],

        ['name' => 'adidas-ball','namear' => 'كره اديداس', 'imgpath'=>'/images/products/adidas-ball.avif', 'price' => '40', 'category_id' => '2', 'second_category_id' => '7','seller_id'=>'2'],
        ['name' => 'nike-shoes','namear' => 'حذاء نايك', 'imgpath'=>'/images/products/nikeshoes.webp', 'price' => '180', 'category_id' => '2', 'second_category_id' => '8','seller_id'=>'2'],
        ['name' => 'swimming-short','namear' => 'شورت سباحه', 'imgpath'=>'/images/products/swimshort.jpg', 'price' => '30', 'category_id' => '2', 'second_category_id' => '9','seller_id'=>'2'],
        ['name' => 't-shirt','namear' => 'كنزه', 'imgpath'=>'/images/products/t-shirt.webp', 'price' => '25', 'category_id' => '2', 'second_category_id' => '10','seller_id'=>'2'],
        ['name' => 'snow gloves','namear' => 'قفازات ثلج', 'imgpath'=>'/images/products/gloves.jpg', 'price' => '15', 'category_id' => '2', 'second_category_id' => '11','seller_id'=>'2'],
        ['name' => 'tennis balls','namear' => 'كرات تينيس', 'imgpath'=>'/images/products/tennis-balls.jpg', 'price' => '15', 'category_id' => '2', 'second_category_id' => '7','seller_id'=>'2'],
        ['name' => 'basketball balls','namear' => 'كرة سلة', 'imgpath'=>'/images/products/basketball.jpg', 'price' => '25', 'category_id' => '2', 'second_category_id' => '7','seller_id'=>'2'],
        ['name' => 'tennis','namear' => 'مضارب تينس', 'imgpath'=>'/images/products/tennis.webp', 'price' => '15', 'category_id' => '2', 'second_category_id' => '7','seller_id'=>'2'],
        ['name' => 'water bottol','namear' => 'عبوة مياه', 'imgpath'=>'/images/products/bottol.webp', 'price' => '15', 'category_id' => '2', 'second_category_id' => '7','seller_id'=>'2'],

        ['name' => 'mascara-p','namear' => 'ماسكرا', 'imgpath'=>'/images/products/mascara.jpg', 'price' => '50', 'category_id' => '3', 'second_category_id' => '12','seller_id'=>'2'],
        ['name' => 'fondation-p','namear' => 'فونديشن', 'imgpath'=>'/images/products/foundation.jpg', 'price' => '60', 'category_id' => '3', 'second_category_id' => '13','seller_id'=>'2'],
        ['name' => 'hamra-p','namear' => 'حمرا', 'imgpath'=>'/images/products/hamra.jpg', 'price' => '70', 'category_id' => '3', 'second_category_id' => '14','seller_id'=>'2'],

        ['name' => 'digital-watch-p','namear' => 'ساعة رقمية', 'imgpath'=>'/images/products/digitalwatch.webp', 'price' => '200', 'category_id' => '4', 'second_category_id' => '15','seller_id'=>'2'],
        ['name' => 'classic-watch-p','namear' => 'ساعة كلاسيكية', 'imgpath'=>'/images/products/classicwatch.webp', 'price' => '300', 'category_id' => '4', 'second_category_id' => '16','seller_id'=>'2'],
        ['name' => 'smart-watch-p','namear' => 'ساعة ذكية', 'imgpath'=>'/images/products/smartwatch.webp', 'price' => '500', 'category_id' => '4', 'second_category_id' => '17','seller_id'=>'2'],
        ['name' => 'expensive-watch-p','namear' => 'ساعة ذكية', 'imgpath'=>'/images/products/expensivewatch.webp', 'price' => '890', 'category_id' => '4', 'second_category_id' => '18','seller_id'=>'2'],
        ['name' => 'kids-watch-p','namear' => 'ساعة أطفال', 'imgpath'=>'/images/products/kidswatch.webp', 'price' => '70', 'category_id' => '4', 'second_category_id' => '19','seller_id'=>'2'],

        ['name' => 't-shirt-p','namear' => 'كنزه', 'imgpath'=>'/images/products/shirt.webp', 'price' => '50', 'category_id' => '5', 'second_category_id' => '20','seller_id'=>'2'],
        ['name' => 'pants-p','namear' => 'بنطال', 'imgpath'=>'/images/products/pants.jpg', 'price' => '40', 'category_id' => '5', 'second_category_id' => '21','seller_id'=>'2'],
        ['name' => 'shorts-p','namear' => 'شورت', 'imgpath'=>'/images/products/tallshort.jpg', 'price' => '25', 'category_id' => '5', 'second_category_id' => '22','seller_id'=>'2'],
        ['name' => 'shoes-p','namear' => 'حذاء محلي', 'imgpath'=>'/images/products/shoes.jpg', 'price' => '250', 'category_id' => '5', 'second_category_id' => '23','seller_id'=>'2'],
        ['name' => 'cap-p','namear' => 'قبعة', 'imgpath'=>'/images/products/cap.jpg', 'price' => '20', 'category_id' => '5', 'second_category_id' => '24','seller_id'=>'2'],
        ['name' => 'sunglass-p','namear' => 'نظارة شمسية', 'imgpath'=>'/images/products/sunglass.jpg', 'price' => '40', 'category_id' => '5', 'second_category_id' => '25','seller_id'=>'2'],
        ['name' => 'socks-p','namear' => 'جوارب', 'imgpath'=>'/images/products/sockes.avif', 'price' => '10', 'category_id' => '5', 'second_category_id' => '26','seller_id'=>'2'],

        ['name' => 'apple-p','namear' => 'تفاح', 'imgpath'=>'/images/products/appels.jpg', 'price' => '4', 'category_id' => '6', 'second_category_id' => '27','seller_id'=>'2'],
        ['name' => 'tomato-p','namear' => 'طماطم', 'imgpath'=>'/images/products/tomatos.webp', 'price' => '5', 'category_id' => '6', 'second_category_id' => '28','seller_id'=>'2'],
        ['name' => 'burger-p','namear' => 'برجر', 'imgpath'=>'/images/products/burger.jpg', 'price' => '25', 'category_id' => '6', 'second_category_id' => '29','seller_id'=>'2'],
        ['name' => 'chips-p','namear' => 'شسبي جبنه', 'imgpath'=>'/images/products/chips.jpg', 'price' => '10', 'category_id' => '6', 'second_category_id' => '30','seller_id'=>'2'],
        ['name' => 'chocolate-p','namear' => 'شوكولاتة مستر بيست', 'imgpath'=>'/images/products/chocolate-bar.webp', 'price' => '20', 'category_id' => '6', 'second_category_id' => '31','seller_id'=>'2'],
    ];
    foreach ($products as $product) {
        Product::create($product);
    }



    $sliders =[
        ['id'=>'1','name'=> 'mainslider', 'description'=>'buy now before lose offer 70%'],
    ];
    foreach ($sliders as $s) {
        Slider::create($s);
    }

    $sliderimages =[
        ['imgpath'=>'/images/one.webp', 'slider_id'=>'1'],
        ['imgpath'=>'/images/two.webp', 'slider_id'=>'1'],
        ['imgpath'=>'/images/three.jpg', 'slider_id'=>'1'],
        ////////////////////////////////////////////////////////
        ['imgpath'=>'/images/products/more/apple1.jpg', 'product_id'=>'31'],
        ['imgpath'=>'/images/products/more/apple2.webp', 'product_id'=>'31'],
        ['imgpath'=>'/images/products/more/apple3.webp', 'product_id'=>'31'],
    ];
    foreach ($sliderimages as $img) {
        Image::create($img);
    }



    }
}
