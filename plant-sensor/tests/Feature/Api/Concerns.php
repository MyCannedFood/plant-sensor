<?php

namespace Tests\Feature\Api;

use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\User;

trait Concerns
{
    /**
     * One user, one plant, one node carrying all three sensors, a short
     * reading history and one open alert.
     *
     * @return array{0: User, 1: Plant, 2: Device}
     */
    protected function scenario(?User $user = null, string $plantName = 'Monstera Room'): array
    {
        $user ??= User::factory()->create();
        $plant = Plant::factory()->for($user)->create(['name' => $plantName]);
        $device = Device::factory()->for($plant)->create(['last_seen_at' => now()]);

        foreach (ThresholdParameter::cases() as $parameter) {
            DeviceSensor::factory()->for($device)->forParameter($parameter)->create();
        }

        Reading::factory()->for($device)->create(['measured_at' => now()->subMinutes(20)]);
        Reading::factory()->for($device)->create(['measured_at' => now()->subMinutes(10)]);
        $latest = Reading::factory()->for($device)->create(['measured_at' => now()]);

        Alert::factory()->for($latest)->create(['parameter' => ThresholdParameter::Co2]);

        return [$user, $plant, $device];
    }
}
