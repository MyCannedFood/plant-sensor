<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantApiTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_the_overview_lists_the_users_plants_with_metrics(): void
    {
        [$user] = $this->scenario();

        $this->actingAs($user)
            ->getJson('/api/plants')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Monstera Room')
            ->assertJsonPath('0.online', true)
            ->assertJsonPath('0.active_alerts', 1)
            ->assertJsonCount(3, '0.metrics')
            ->assertJsonStructure([[
                'id', 'name', 'species', 'location', 'online', 'last_seen_at', 'readings_today', 'active_alerts',
                'metrics' => [['parameter', 'label', 'unit', 'decimals', 'value', 'status', 'trend', 'threshold' => ['min', 'max']]],
            ]]);
    }

    public function test_the_overview_never_shows_another_users_plants(): void
    {
        $this->scenario(plantName: 'Somebody Else');
        $me = User::factory()->create();

        $this->actingAs($me)->getJson('/api/plants')->assertOk()->assertExactJson([]);
    }

    public function test_a_plant_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/plants', [
                'name' => '  Peace Lily ',
                'species' => 'Spathiphyllum wallisii',
                'location' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Peace Lily')
            ->assertJsonPath('location', null)
            ->assertJsonPath('metrics', []);

        $this->assertDatabaseHas('plants', [
            'user_id' => $user->id,
            'name' => 'Peace Lily',
            'location' => null,
        ]);
    }

    public function test_a_plant_requires_a_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/plants', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Give this plant a name.']);
    }

    public function test_the_monitor_payload_has_metrics_devices_alerts_and_counts(): void
    {
        [$user, $plant, $device] = $this->scenario();

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('primary_device_id', $device->id)
            ->assertJsonCount(3, 'metrics')
            ->assertJsonCount(1, 'devices')
            ->assertJsonPath('devices.0.readings_today', 3)
            ->assertJsonCount(1, 'recent_alerts')
            ->assertJsonPath('recent_alerts.0.status', 'watch')
            ->assertJsonPath('counts', ['devices' => 1, 'readings' => 3, 'alerts' => 1])
            ->assertJsonMissingPath('devices.0.api_token');
    }

    public function test_a_plant_with_no_device_still_loads(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->for($user)->create();

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('primary_device_id', null)
            ->assertJsonPath('metrics', [])
            ->assertJsonPath('devices', []);
    }

    public function test_another_users_plant_answers_not_found(): void
    {
        [, $plant] = $this->scenario();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/plants/{$plant->id}")->assertNotFound();
        $this->actingAs($intruder)->patchJson("/api/plants/{$plant->id}", ['name' => 'Mine now'])->assertNotFound();
        $this->actingAs($intruder)->deleteJson("/api/plants/{$plant->id}")->assertNotFound();

        $this->assertModelExists($plant);
    }

    public function test_plant_details_can_be_updated(): void
    {
        [$user, $plant] = $this->scenario();

        $this->actingAs($user)
            ->patchJson("/api/plants/{$plant->id}", [
                'name' => 'Big Monstera',
                'species' => null,
                'location' => 'Hall',
                'notes' => 'Water weekly',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Big Monstera')
            ->assertJsonPath('notes', 'Water weekly');

        $this->assertDatabaseHas('plants', ['id' => $plant->id, 'name' => 'Big Monstera', 'species' => null]);
    }

    public function test_deleting_a_plant_cascades_to_devices_and_readings(): void
    {
        [$user, $plant, $device] = $this->scenario();

        $this->actingAs($user)->deleteJson("/api/plants/{$plant->id}")->assertNoContent();

        $this->assertModelMissing($plant);
        $this->assertModelMissing($device);
        $this->assertSame(0, Reading::query()->where('device_id', $device->id)->count());
        $this->assertSame(0, Device::query()->count());
    }
}
