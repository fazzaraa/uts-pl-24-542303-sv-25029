<?php

use App\Http\Middleware\CountRequests;
use App\Http\Middleware\RequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Menempelkan request_id, method, dan path ke setiap baris log pada request API.
        $middleware->api(prepend: [RequestContext::class, CountRequests::class]);

        // API tidak punya halaman login: tamu tanpa token tidak dialihkan, cukup dibalas 401.
        // Tanpa ini, Laravel mencari rute 'login' yang tidak ada dan berujung 500.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua rute /api selalu dibalas JSON. Tanpa ini, permintaan tanpa header
        // 'Accept: application/json' dan tanpa token berujung 500 (Laravel mencoba
        // mengalihkan ke rute 'login' yang tidak ada), padahal seharusnya 401.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        // Validasi gagal (422): catat nama kolom dan jenis aturan yang dilanggar,
        // TANPA nilai yang dikirim pengguna (bisa berisi password atau data pribadi).
        $exceptions->render(function (ValidationException $e, Request $request) {
            Log::warning('validation.failed', [
                'actor' => $request->user()?->id ?? 'anonim',
                'action' => $request->method().' '.$request->path(),
                'cause' => 'input tidak lolos validasi',
                'fields' => collect($e->validator->failed())
                    ->map(fn ($rules) => array_map('strtolower', array_keys($rules)))
                    ->all(),
            ]);

            return null; // balasan 422 bawaan Laravel tetap dipakai
        });

        // Kegagalan basis data (mis. MySQL mati): pesan QueryException memuat SQL
        // beserta nilai binding (email, hash token), jadi tidak dicatat mentah-mentah.
        // Yang dicatat hanya jenis kegagalan dan pesan driver untuk masalah koneksi.
        $exceptions->report(function (QueryException $e) {
            $driverMessage = $e->getPrevious()?->getMessage() ?? '';
            $isConnection = (bool) preg_match('/\[(2002|2006|2013)\]|getaddrinfo|Connection refused/i', $driverMessage);

            Log::error('database.failed', [
                'actor' => 'system',
                'action' => 'mengakses basis data',
                'cause' => $isConnection
                    ? 'tidak dapat terhubung ke basis data: '.mb_substr($driverMessage, 0, 200)
                    : 'query gagal (SQLSTATE '.($e->errorInfo[0] ?? $e->getCode()).')',
            ]);
        })->stop();
    })->create();
