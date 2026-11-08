<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->prependToPriorityList(SubstituteBindings::class, CheckAbilities::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, CheckForAnyAbility::class);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => response()->json([
            'message' => 'Kimlik doğrulaması gerekli veya oturum süresi doldu.',
        ], 401));

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => response()->json([
            'message' => 'Kayıt bulunamadı.',
        ], 404));
    })->create();
