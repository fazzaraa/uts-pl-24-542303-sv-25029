<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class StructuredLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_important_flows_are_logged_with_context_and_without_secrets(): void
    {
        $handler = new TestHandler();
        Log::getLogger()->pushHandler($handler);

        $register = $this->postJson('/api/register', [
            'name' => 'Galih', 'email' => 'galih@example.com', 'password' => 'rahasia123',
        ])->assertCreated();
        $token = $register->json('token');

        $this->postJson('/api/login', ['email' => 'galih@example.com', 'password' => 'salahsalah'])
            ->assertUnauthorized();

        Sanctum::actingAs(User::first());
        $this->postJson('/api/books', ['title' => '', 'status' => 'xx'])->assertUnprocessable();
        $this->postJson('/api/books', [
            'title' => 'Judul Rahasia', 'author' => 'Penulis', 'status' => 'reading',
        ])->assertCreated();

        $this->assertTrue($handler->hasInfoThatContains('auth.registered'));
        $this->assertTrue($handler->hasWarningThatContains('auth.login_failed'));
        $this->assertTrue($handler->hasWarningThatContains('validation.failed'));
        $this->assertTrue($handler->hasInfoThatContains('book.created'));

        foreach ($handler->getRecords() as $record) {
            foreach (['actor', 'action', 'cause'] as $key) {
                if (str_starts_with($record->message, 'auth.') || str_starts_with($record->message, 'book.') || str_starts_with($record->message, 'validation.')) {
                    $this->assertArrayHasKey($key, $record->context, "{$record->message} tanpa {$key}");
                }
            }
        }

        $all = json_encode(array_map(fn ($r) => [$r->message, $r->context], $handler->getRecords()));
        foreach (['rahasia123', 'salahsalah', 'galih@example.com', $token, 'Judul Rahasia'] as $secret) {
            $this->assertStringNotContainsString($secret, $all);
        }
    }
}
