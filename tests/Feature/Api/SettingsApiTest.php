<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_is_available(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_settings_response_is_android_compatible_and_ads_are_disabled(): void
    {
        $this->getJson('/api/settings/test-code')
            ->assertOk()
            ->assertJsonPath('app_name', 'El-Nemr TV')
            ->assertJsonPath('default_substitle_option', 'None')
            ->assertJsonPath('ad_banner', 0)
            ->assertJsonPath('startapp_banner', 0)
            ->assertJsonMissingPath('stripe_secret_key')
            ->assertJsonMissingPath('purchase_key');
    }

    public function test_public_database_values_override_defaults_but_private_values_never_leak(): void
    {
        AppSetting::create(['key' => 'app_name', 'value' => 'My TV', 'is_public' => true]);
        AppSetting::create(['key' => 'stripe_secret_key', 'value' => 'secret', 'is_public' => false]);

        $this->getJson('/api/settings/legacy-code')
            ->assertOk()
            ->assertJsonPath('app_name', 'My TV')
            ->assertJsonMissingPath('stripe_secret_key');
    }
}
