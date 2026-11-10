<?php

namespace App\Http\Middleware;
use \App\Models\User;
use illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomSellerAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = User::where('role', 'seller')->where('id', Auth::user()->id)->exists();
        if ($isAdmin) {
            return $next($request);
        } else {
            return redirect('/');
        }
    }
}
