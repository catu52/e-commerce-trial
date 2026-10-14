<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_returns_token_and_client(): void
    {
        $this->postJson('/api/v1/client/register', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertCreated()
            ->assertJsonPath('client.name', 'Ada Lovelace')
            ->assertJsonPath('client.email', 'ada@example.com')
            ->assertJsonStructure(['token', 'client' => ['id', 'name', 'email']]);
    }

    public function test_login_me_and_logout_flow(): void
    {
        Client::create([
            'first_name' => 'Demo', 'last_name' => 'Client', 'email' => 'client@example.com', 'password' => 'password',
        ]);

        $token = $this->postJson('/api/v1/client/login', ['email' => 'client@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('client.name', 'Demo Client')
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/client/me')
            ->assertOk()->assertJsonPath('client.email', 'client@example.com');

        $this->withToken($token)->postJson('/api/v1/client/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejects_bad_credentials_and_me_requires_auth(): void
    {
        $this->postJson('/api/v1/client/login', ['email' => 'x@example.com', 'password' => 'nope-nope'])
            ->assertUnprocessable();

        $this->getJson('/api/v1/client/me')->assertUnauthorized();
    }
}
