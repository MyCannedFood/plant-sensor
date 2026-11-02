<?php

namespace Tests\Feature\Api;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceThresholdApiTest extends TestCase
{
    use Concerns, RefreshDatabase;

    public function test_thresholds_can_be_saved(): void
    {
        [$user, , $device] = $this->scenario();

        $this->actingAs($user)
            ->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => 350, 'max' => 1200]],
            ])
            ->assertOk()
            ->assertJsonPath('thresholds.co2', ['min' => 350, 'max' => 1200]);

        $this->assertDatabaseHas('thresholds', [
            'device_id' => $device->id,
            'parameter' => 'co2',
            'min_value' => 350,
            'max_value' => 1200,
        ]);
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        [$user, , $device] = $this->scenario();

        foreach ([[300, 1000], [400, 1100]] as [$min, $max]) {
            $this->actingAs($user)->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => $min, 'max' => $max]],
            ])->assertOk();
        }

        $this->assertSame(1, $device->thresholds()->count());
        $this->assertSame([400.0, 1100.0], $device->thresholdsFor(ThresholdParameter::Co2));
    }

    public function test_non_numeric_and_inverted_bounds_are_rejected(): void
    {
        [$user, , $device] = $this->scenario();

        $this->actingAs($user)
            ->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => 100, 'max' => 'lots']],
            ])
            ->assertJsonValidationErrors('thresholds.co2.max');

        $this->actingAs($user)
            ->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => 1500, 'max' => 400]],
            ])
            ->assertJsonValidationErrors('thresholds.co2.min');
    }

    public function test_a_parameter_the_device_does_not_carry_is_rejected(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->for(Plant::factory()->for($user))->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Temperature)->create();

        $this->actingAs($user)
            ->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => 400, 'max' => 1000]],
            ])
            ->assertJsonValidationErrors('thresholds.co2');
    }

    public function test_another_users_device_cannot_be_changed(): void
    {
        [, , $device] = $this->scenario();

        $this->actingAs(User::factory()->create())
            ->putJson("/api/devices/{$device->id}/thresholds", [
                'thresholds' => ['co2' => ['min' => 1, 'max' => 2]],
            ])
            ->assertNotFound();

        $this->assertSame(0, $device->thresholds()->count());
    }
}
