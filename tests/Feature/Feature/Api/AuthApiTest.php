<?php

namespace Tests\Feature\Feature\Api;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_access_token_and_refresh_cookie(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Secure1234',
            'password_confirmation' => 'Secure1234',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'full_name',
                        'username',
                        'email',
                        'profile_image_url',
                        'created_at',
                    ],
                    'access_token',
                    'token_type',
                    'access_token_expires_in',
                    'refresh_token_expires_at',
                ],
            ])
            ->assertCookie('refresh_token');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertDatabaseCount('refresh_tokens', 1);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('Secure1234'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_me_endpoint_requires_valid_access_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertUnauthorized();
    }

    public function test_refresh_rotates_refresh_token_and_returns_new_access_token(): void
    {
        $loginResponse = $this->postJson('/api/auth/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => 'Secure1234',
            'password_confirmation' => 'Secure1234',
        ]);

        $refreshCookie = collect($loginResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'refresh_token');

        $this->assertNotNull($refreshCookie);
        $this->assertDatabaseCount('refresh_tokens', 1);

        $refreshResponse = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshCookie?->getValue() ?? '',
        ]);

        $refreshResponse
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user',
                    'access_token',
                    'token_type',
                    'access_token_expires_in',
                    'refresh_token_expires_at',
                ],
            ])
            ->assertCookie('refresh_token');

        $this->assertEquals(2, RefreshToken::query()->count());
        $this->assertEquals(1, RefreshToken::query()->whereNotNull('revoked_at')->count());
    }
}
