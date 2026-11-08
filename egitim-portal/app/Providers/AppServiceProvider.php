<?php

namespace App\Providers;

use App\Media\MediaManager;
use App\Models\DocumentContent;
use App\Models\LinkContent;
use App\Models\User;
use App\Models\VideoContent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaManager::class);
    }

    public function boot(): void
    {
        Route::pattern('education', '[0-9]+');
        Route::pattern('assignment', '[0-9]+');
        Route::pattern('content', '[0-9]+');
        Route::pattern('id', '[0-9]+');

        Relation::enforceMorphMap([
            'video' => VideoContent::class,
            'document' => DocumentContent::class,
            'link' => LinkContent::class,
            'user' => User::class, // Sanctum tokenable_type
        ]);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Çok fazla giriş denemesi yaptınız. Lütfen bir dakika sonra tekrar deneyin.',
            ], 429, $headers)));
    }
}
