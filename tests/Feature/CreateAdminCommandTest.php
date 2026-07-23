<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_promotes_an_existing_user_to_admin(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'old-password',
        ]);

        $this->artisan('admin:create', [
            'email' => 'admin@example.com',
            '--name' => 'Administrator',
            '--password' => 'new-password',
        ])->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }
}
