<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menghitung permintaan API berdasarkan kelas status (2xx, 3xx, 4xx, 5xx).
 * Hasilnya dibaca Prometheus lewat GET /api/metrics, lalu dipakai Grafana
 * untuk menjawab "berapa permintaan per detik yang diterima aplikasi?".
 *
 * Yang dihitung hanya permintaan pengguna: /api/health (dipanggil healthcheck
 * tiap 10 detik) dan /api/metrics (dipanggil Prometheus) sengaja dilewati.
 * Hanya angka yang disimpan, tidak ada IP, header, atau isi permintaan.
 */
class CountRequests
{
    public const CLASSES = ['2xx', '3xx', '4xx', '5xx'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->is('api/health', 'api/metrics')) {
            return;
        }

        $class = intdiv($response->getStatusCode(), 100).'xx';
        if (! in_array($class, self::CLASSES, true)) {
            return;
        }

        try {
            $key = 'metrics:http_requests:'.$class;
            Cache::add($key, 0);
            Cache::increment($key);
        } catch (\Throwable) {
            // Gagal mencatat metrik tidak boleh mengganggu permintaan pengguna.
        }
    }
}
