<?php

namespace Tests\Feature\Api;

use App\Models\AccessToken;
use App\Models\Genre;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_admin_api(): void
    {
        [$plain] = $this->tokenFor('user');

        $this->withToken($plain)->getJson('/api/admin/media')->assertForbidden();
    }

    public function test_admin_can_create_update_and_soft_delete_content(): void
    {
        [$plain] = $this->tokenFor('admin');
        $genre = Genre::create(['name' => 'Drama', 'slug' => 'drama']);

        $created = $this->withToken($plain)->postJson('/api/admin/media', [
            'type' => 'movie',
            'title' => 'Admin Movie',
            'slug' => 'admin-movie',
            'is_published' => true,
            'genre_ids' => [$genre->id],
        ])->assertCreated()
            ->assertJsonPath('title', 'Admin Movie')
            ->assertJsonPath('genres.0.name', 'Drama');

        $id = $created->json('id');

        $this->withToken($plain)->putJson("/api/admin/media/{$id}", [
            'title' => 'Updated Movie',
            'is_featured' => true,
        ])->assertOk()->assertJsonPath('title', 'Updated Movie');

        $this->withToken($plain)->deleteJson("/api/admin/media/{$id}")->assertOk();
        $this->assertSoftDeleted('media', ['id' => $id]);
    }

    public function test_admin_can_manage_genres(): void
    {
        [$plain] = $this->tokenFor('admin');

        $this->withToken($plain)->postJson('/api/admin/genres', [
            'name' => 'Action',
            'slug' => 'action',
        ])->assertCreated();

        $this->withToken($plain)->getJson('/api/admin/genres')
            ->assertOk()->assertJsonPath('data.0.slug', 'action');
    }

    private function tokenFor(string $role): array
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        $plain = Str::random(80);
        AccessToken::create([
            'user_id' => $user->id,
            'name' => 'android',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
        ]);

        return [$plain, $user];
    }
}
