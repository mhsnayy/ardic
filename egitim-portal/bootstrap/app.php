<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

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
