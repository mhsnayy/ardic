<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::pattern('education', '[0-9]+');
        Route::pattern('assignment', '[0-9]+');
        Route::pattern('id', '[0-9]+');

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Çok fazla giriş denemesi yaptınız. Lütfen bir dakika sonra tekrar deneyin.',
            ], 429, $headers)));
    }
}
