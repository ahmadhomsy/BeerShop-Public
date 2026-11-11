<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $limit = (int) env('RATE_LIMIT_API', 60);
            return Limit::perMinute($limit)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Load modular v1 routes
            $modules = ['Users', 'Products', 'Orders', 'Notifications'];
            foreach ($modules as $module) {
                $routeFile = app_path("Modules/{$module}/Routes/api.php");
                if (file_exists($routeFile)) {
                    Route::middleware('api')
                        ->prefix('api/v1')
                        ->group($routeFile);
                }
            }

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
