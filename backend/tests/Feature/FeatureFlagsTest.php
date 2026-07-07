<?php

namespace Tests\Feature;

use App\Modules\Shared\Support\FeatureFlags;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    public function test_all_v2_increments_default_to_disabled(): void
    {
        $features = new FeatureFlags;

        $this->assertFalse($features->enabled('webhooks'));
        $this->assertFalse($features->enabled('releases'));
        $this->assertFalse($features->enabled('deployments'));
        $this->assertFalse($features->enabled('notifications'));
        $this->assertFalse($features->enabled('incidents'));
        $this->assertFalse($features->enabled('ai'));
    }

    public function test_a_flag_can_be_enabled_through_configuration(): void
    {
        config()->set('releaselens.features.webhooks', true);

        $this->assertTrue((new FeatureFlags)->enabled('webhooks'));
    }

    public function test_disabled_feature_route_returns_404_without_leaking_details(): void
    {
        Route::middleware(['api', 'feature:webhooks'])
            ->get('/api/v1/_test/webhooks-only', fn () => response()->json(['data' => 'ok']));

        $response = $this->getJson('/api/v1/_test/webhooks-only');

        $response
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_enabled_feature_route_passes_through(): void
    {
        config()->set('releaselens.features.webhooks', true);

        Route::middleware(['api', 'feature:webhooks'])
            ->get('/api/v1/_test/webhooks-only', fn () => response()->json(['data' => 'ok']));

        $response = $this->getJson('/api/v1/_test/webhooks-only');

        $response->assertOk()->assertJsonPath('data', 'ok');
    }
}
