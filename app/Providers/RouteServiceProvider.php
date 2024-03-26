<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const FARM_ROUTE_BASE = '/farm/{farm}';

    public const FARM_SEASON_ROUTE_BASE = self::FARM_ROUTE_BASE.'/season/{farmSeason}';

    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        $this->configureRateLimiting();

        $this->routes(function () {
            // API V1
            $p = 'v1';
            $m = ['api', 'api_version:v1'];
            $n = $this->namespace.'\v1';
            Route::prefix($p)->middleware($m)->namespace($n)->group(base_path('routes/api/v1/auth.php'));
            Route::prefix($p)->namespace($n)->group(base_path('routes/api/v1/jokes.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
