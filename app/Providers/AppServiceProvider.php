<?php

namespace App\Providers;

use App\Models\GbpClient;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceHttps();
        }

        Route::model('client', GbpClient::class);

        Gate::define('manage-gbp', fn (User $user): bool => (bool) $user->is_admin);

        RateLimiter::for('onboarding', fn (Request $request) => Limit::perMinute(40)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email'))));
    }
}
