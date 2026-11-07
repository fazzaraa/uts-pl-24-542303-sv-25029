<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_returns_200_when_database_and_cache_are_up(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('checks.database.status', 'up')
            ->assertJsonPath('checks.cache.status', 'up');
    }

    public function test_health_returns_503_and_names_database_when_it_is_down(): void
    {
        DB::shouldReceive('select')->once()
            ->andThrow(new \PDOException('SQLSTATE[HY000] [2002] Connection refused'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'unhealthy')
            ->assertJsonPath('failed', ['database'])
            ->assertJsonPath('checks.database.status', 'down')
            ->assertJsonPath('checks.cache.status', 'up');
    }

    public function test_health_returns_503_and_names_cache_when_it_is_down(): void
    {
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('cache mati'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('failed', ['cache'])
            ->assertJsonPath('checks.cache.status', 'down')
            ->assertJsonPath('checks.database.status', 'up');
    }
}
