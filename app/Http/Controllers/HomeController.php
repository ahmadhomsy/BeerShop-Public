<?php

namespace App\Http\Controllers;
use \App\Models\User;
use illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index() {
        $isAdmin = User::where('role', 'admin')->where('id', Auth::user()->id)->exists();
        if ($isAdmin) {
            return redirect('/showdash');
        } else {
            return redirect('/');
        }
    }
    //return redirect('/');

}
