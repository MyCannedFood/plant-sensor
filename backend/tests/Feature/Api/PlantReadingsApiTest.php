<?php

namespace Tests\Feature\Api;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\Threshold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantReadingsApiTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_the_series_defaults_to_the_first_metric_over_24_hours(): void
    {
        [$user, $plant] = $this->scenario();

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}/readings")
            ->assertOk()
            ->assertJsonPath('range', '24h')
            ->assertJsonCount(3, 'points')
            ->assertJsonStructure(['parameter', 'label', 'unit', 'decimals', 'threshold' => ['min', 'max'], 'points' => [[0, 1]]]);
    }

    public function test_the_range_cuts_off_older_points(): void
    {
        [$user, $plant] = $this->scenario();

        // The scenario readings are 0, 10 and 20 minutes old. Add one from two
        // days ago: only the 7-day range should include it.
        Reading::factory()->for($plant->devices()->first())->create(['measured_at' => now()->subDays(2)]);

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}/readings?metric=temperature&range=1h")
            ->assertOk()
            ->assertJsonPath('parameter', 'temperature')
            ->assertJsonCount(3, 'points');

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}/readings?metric=temperature&range=7d")
            ->assertJsonCount(4, 'points');
    }

    public function test_a_metric_the_device_does_not_carry_is_rejected(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->for($user)->create();
        $device = Device::factory()->for($plant)->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Temperature)->create();

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}/readings?metric=co2")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metric');
    }

    public function test_the_band_prefers_the_device_override_over_the_global_default(): void
    {
        [$user, $plant, $device] = $this->scenario();

        Threshold::factory()->globalDefault()->forParameter(ThresholdParameter::Co2, 400, 1500)->create();
        Threshold::factory()->for($device)->forParameter(ThresholdParameter::Co2, 500, 1200)->create();

        $this->actingAs($user)
            ->getJson("/api/plants/{$plant->id}/readings?metric=co2")
            ->assertJsonPath('threshold', ['min' => 500, 'max' => 1200]);
    }
}
