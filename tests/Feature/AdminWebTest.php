<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_and_admin_can_open_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('تسجيل دخول');

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('لوحة التحكم');
    }

    public function test_regular_user_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_login_with_email_and_password(): void
    {
        $admin = User::factory()->create([
            'email' => 'web-admin@example.com',
            'password' => 'strong-password',
        ]);
        $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();

        $this->post('/admin/login', [
            'email' => 'web-admin@example.com',
            'password' => 'strong-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_page_is_not_cached(): void
    {
        $response = $this->get('/admin/login');
        $response->assertHeader('cache-control');
    }

    public function test_admin_can_create_content_from_web_form(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->post('/admin/media', [
            'type' => 'movie',
            'title' => 'Web Movie',
            'slug' => 'web-movie',
            'is_published' => '1',
        ])->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseHas('media', [
            'title' => 'Web Movie',
            'is_published' => true,
        ]);
    }

    public function test_admin_can_add_season_episode_and_stream_from_catalog_page(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $series = Media::create(['type' => 'series', 'title' => 'Show', 'slug' => 'web-show']);

        $this->actingAs($admin)->post("/admin/media/{$series->id}/seasons", [
            'season_number' => 1,
        ])->assertRedirect();
        $season = $series->seasons()->firstOrFail();

        $this->actingAs($admin)->post("/admin/seasons/{$season->id}/episodes", [
            'episode_number' => 1, 'name' => 'Pilot',
        ])->assertRedirect();
        $episode = $season->episodes()->firstOrFail();

        $this->actingAs($admin)->post("/admin/episodes/{$episode->id}/streams", [
            'name' => 'Main', 'url' => 'https://video.example/pilot.m3u8',
        ])->assertRedirect();

        $this->actingAs($admin)->get("/admin/media/{$series->id}/catalog")
            ->assertOk()->assertSee('Pilot')->assertSee('video.example');
    }
}
