<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricsTest extends TestCase
{
    public function test_metrik_menghitung_permintaan_menurut_kelas_status(): void
    {
        $this->getJson('/api/books')->assertStatus(401);   // 4xx
        $this->getJson('/api/books')->assertStatus(401);   // 4xx
        $this->getJson('/api/health');                      // dilewati
        $this->getJson('/api/metrics');                     // dilewati

        $body = $this->get('/api/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->getContent();

        $this->assertStringContainsString('# TYPE pustaka_http_requests_total counter', $body);
        $this->assertStringContainsString('pustaka_http_requests_total{code="4xx"} 2', $body);
        $this->assertStringContainsString('pustaka_http_requests_total{code="2xx"} 0', $body);
        $this->assertStringContainsString('pustaka_http_requests_total{code="5xx"} 0', $body);
    }

    public function test_api_tanpa_token_dan_tanpa_header_accept_dibalas_401_bukan_500(): void
    {
        $this->get('/api/books')->assertStatus(401);
    }
}
