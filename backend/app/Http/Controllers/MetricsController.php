<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CountRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Menyajikan metrik aplikasi dalam format teks Prometheus.
 * Isinya hanya angka agregat, tidak ada data pengguna.
 */
class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            '# HELP pustaka_http_requests_total Jumlah permintaan API pengguna menurut kelas status.',
            '# TYPE pustaka_http_requests_total counter',
        ];

        foreach (CountRequests::CLASSES as $class) {
            $count = (int) Cache::get('metrics:http_requests:'.$class, 0);
            $lines[] = sprintf('pustaka_http_requests_total{code="%s"} %d', $class, $count);
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
