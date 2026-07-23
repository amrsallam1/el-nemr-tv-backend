<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_use_access_token(): void
    {
        $register = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'strong-password',
        ])->assertCreated()->assertJsonStructure(['access_token', 'refresh_token']);

        $this->withToken($register->json('access_token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'test@example.com')
            ->assertJsonPath('premuim', 0);

        $this->assertNotEquals(
            $register->json('access_token'),
            User::first()->accessTokens()->where('name', 'android')->value('token_hash'),
        );
    }

    public function test_existing_user_can_login_and_refresh_token(): void
    {
        User::create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => 'strong-password',
        ]);

        $login = $this->postJson('/api/login', [
            'username' => 'member@example.com',
            'password' => 'strong-password',
        ])->assertOk();

        $this->postJson('/api/refresh', ['refresh_token' => $login->json('refresh_token')])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_invalid_password_and_missing_token_are_rejected(): void
    {
        User::create([
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/login', [
            'username' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->getJson('/api/user')->assertUnauthorized();
    }
}
