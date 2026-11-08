<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            // Signed browser endpoints: no web middleware group (session/CSRF), no /api prefix.
            Route::group([], base_path('routes/media.php'));
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // API only: never redirect guests to a (non-existent) login route.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'uploads/*', 'media/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (InvalidSignatureException $e, Request $request) => response()->json(
            ['message' => 'Bağlantı geçersiz veya süresi dolmuş.'], 403,
        ));
        $exceptions->render(fn (AuthenticationException $e, Request $request) => response()->json(
            ['message' => 'Oturum geçersiz veya süresi dolmuş.'], 401,
        ));
        $exceptions->render(fn (MissingAbilityException $e, Request $request) => response()->json(
            ['message' => 'Bu işlem için yetkiniz yok.'], 403,
        ));
        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => response()->json(
            ['message' => 'Kayıt bulunamadı.'], 404,
        ));
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => response()->json(
            ['message' => 'Kayıt bulunamadı.'], 404,
        ));
        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $request) => response()->json(
            ['message' => 'Çok fazla deneme yapıldı. Lütfen biraz sonra tekrar deneyin.'], 429,
            array_filter(['Retry-After' => $e->getHeaders()['Retry-After'] ?? null]),
        ));
    })->create();
