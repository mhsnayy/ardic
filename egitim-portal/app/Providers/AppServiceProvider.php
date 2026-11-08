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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaManager::class);
    }

    public function boot(): void
    {
        // Every absolute URL this API hands out (signed upload/playback URLs) must be the public
        // address (APP_URL), not the internal host the request arrived on (e.g. http://egitim-portal:8000).
        if ($root = config('app.url')) {
            URL::forceRootUrl($root);
            if ($scheme = parse_url($root, PHP_URL_SCHEME)) {
                URL::forceScheme($scheme);
            }
        }

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

        // Keyed by token hash (behind Next every request shares one IP).
        RateLimiter::for('learn', fn (Request $request) => Limit::perMinute(120)
            ->by($request->header('X-Learning-Token')
                ? 'learn:'.hash('sha256', (string) $request->header('X-Learning-Token'))
                : 'learn-ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Çok fazla giriş denemesi yaptınız. Lütfen bir dakika sonra tekrar deneyin.',
            ], 429, $headers)));
    }
}
