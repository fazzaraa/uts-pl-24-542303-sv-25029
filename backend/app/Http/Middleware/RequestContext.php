<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menempelkan konteks yang sama ke seluruh log dalam satu request,
 * supaya beberapa baris log dari satu kejadian mudah dikaitkan.
 * Sengaja tidak mencatat IP, header, body, atau token.
 */
class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();

        Log::withContext([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
