<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertApiTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_history_lists_the_users_alerts_with_room_and_node(): void
    {
        [$user, , $device] = $this->scenario();

        $this->actingAs($user)
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonPath('filter', 'all')
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.plant.name', 'Monstera Room')
            ->assertJsonPath('alerts.0.device.name', $device->name);
    }

    public function test_filters_partition_the_history(): void
    {
        [$user, , $device] = $this->scenario();
        $reading = Reading::factory()->for($device)->create(['measured_at' => now()->subHour()]);

        Alert::factory()->for($reading)->critical()->create();
        Alert::factory()->for($reading)->critical()->resolved()->create();

        $response = $this->actingAs($user)->getJson('/api/alerts?filter=alert')->assertOk();

        $response->assertJsonPath('counts', ['all' => 3, 'alert' => 1, 'watch' => 1, 'resolved' => 1])
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.status', 'alert');

        $this->actingAs($user)->getJson('/api/alerts?filter=resolved')
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.status', 'good');
    }

    public function test_other_users_alerts_never_leak(): void
    {
        $this->scenario(plantName: 'Somebody Else');
        [$me] = $this->scenario(plantName: 'Mine');

        $this->actingAs($me)->getJson('/api/alerts')
            ->assertJsonCount(1, 'alerts')
            ->assertJsonPath('alerts.0.plant.name', 'Mine');

        $this->actingAs($me)->getJson('/api/alerts/summary')->assertExactJson(['active' => 1]);
    }

    public function test_an_unknown_filter_is_rejected(): void
    {
        [$user] = $this->scenario();

        $this->actingAs($user)->getJson('/api/alerts?filter=everything')->assertJsonValidationErrors('filter');
    }
}
