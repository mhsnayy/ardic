<?php

namespace App\Http\Middleware;

use App\Services\LearningLinkService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves X-Learning-Token to its assignment. Every failure looks identical (02 §6). */
class ResolveLearningToken
{
    public const INVALID_MESSAGE = 'Bu öğrenme linki geçersiz ya da süresi dolmuş.';

    public function __construct(private LearningLinkService $links) {}

    public function handle(Request $request, Closure $next): Response
    {
        $link = $this->links->resolve($request->header('X-Learning-Token'));

        if (! $link) {
            return response()->json(['message' => self::INVALID_MESSAGE], 404);
        }

        $request->attributes->set('assignment', $link->assignment);

        return $next($request);
    }
}
