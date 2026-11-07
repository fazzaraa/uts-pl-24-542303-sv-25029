<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Health check yang benar-benar memeriksa dependensi:
 * - database: menjalankan query sungguhan (SELECT 1)
 * - cache   : menulis lalu membaca kembali satu nilai uji
 * Balasan 200 jika semua sehat, 503 jika ada yang bermasalah beserta nama bagiannya.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check('database', 'tidak dapat menjalankan query ke basis data', function () {
                DB::select('select 1');
            }),
            'cache' => $this->check('cache', 'tidak dapat menulis atau membaca cache', function () {
                $key = 'health:probe:'.Str::random(8);
                $value = Str::random(16);
                Cache::put($key, $value, 10);

                if (Cache::get($key) !== $value) {
                    throw new \RuntimeException('nilai uji cache tidak terbaca kembali');
                }
                Cache::forget($key);
            }),
        ];

        $failed = array_keys(array_filter($checks, fn ($c) => $c['status'] === 'down'));
        $healthy = $failed === [];

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'failed' => $failed,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function check(string $name, string $problem, callable $probe): array
    {
        $start = hrtime(true);

        try {
            $probe();

            return ['status' => 'up', 'latency_ms' => round((hrtime(true) - $start) / 1e6, 1)];
        } catch (Throwable $e) {
            // Pesan driver hanya dicatat untuk kegagalan koneksi (memuat nama host, tanpa
            // kredensial). Pesan QueryException biasa tidak dicatat karena memuat SQL dan binding.
            $driverMessage = ($e instanceof QueryException ? $e->getPrevious() : $e)?->getMessage() ?? '';
            $isConnection = (bool) preg_match('/\[(2002|2006|2013)\]|getaddrinfo|Connection refused/i', $driverMessage);

            // Log memuat siapa/apa/sebabnya; balasan publik hanya memuat ringkasan umum.
            Log::error('health.check_failed', array_filter([
                'actor' => 'system',
                'action' => 'health check',
                'component' => $name,
                'cause' => $isConnection ? 'koneksi ke '.$name.' gagal: '.mb_substr($driverMessage, 0, 200) : $problem,
                'exception' => class_basename($e),
            ]));

            return ['status' => 'down', 'error' => $problem];
        }
    }
}
