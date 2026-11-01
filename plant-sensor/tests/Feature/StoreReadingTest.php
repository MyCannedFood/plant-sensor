<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Reading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The real POST /api/readings endpoint and its three outcomes: a fresh
 * instant stores and returns 201, an identical retry of a deliverable the
 * device never saw the answer to returns 200 without a second row, and the
 * same instant carrying different values is surfaced as a 409 - the
 * signature of a stuck clock producing two snapshots for one moment.
 */
class StoreReadingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a device carrying the given parameters.
     */
    private function deviceWith(ThresholdParameter ...$parameters): Device
    {
        $device = Device::factory()->create();

        foreach ($parameters as $parameter) {
            DeviceSensor::factory()->for($device)->forParameter($parameter)->create();
        }

        return $device;
    }

    /**
     * A payload every dual-sensor device can send.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'co2' => 850,
            'temperature' => 21.4,
            'humidity' => 61.5,
            'measured_at' => now()->subMinute()->toIso8601String(),
        ];
    }

    private function postReading(Device $device, array $payload): TestResponse
    {
        return $this->postJson('/api/readings', $payload, [
            'X-Device-Token' => $device->generateToken(),
        ]);
    }

    public function test_a_valid_payload_stores_a_reading_and_returns_201(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $response = $this->postReading($device, $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'device_id',
                'measured_at',
                'received_at',
                'co2',
                'temperature',
                'humidity',
                'server_time',
            ])
            ->assertJsonPath('device_id', $device->id)
            ->assertJsonPath('co2', 850)
            ->assertJsonPath('temperature', 21.4)
            ->assertJsonPath('humidity', 61.5);

        $this->assertDatabaseHas('readings', [
            'device_id' => $device->id,
            'co2' => 850,
            'temperature' => 21.40,
            'humidity' => 61.50,
        ]);
    }

    public function test_the_response_timestamps_are_iso8601_with_a_server_clock_marker(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $response = $this->postReading($device, $this->validPayload());

        Carbon::parse($response->json('measured_at'));
        Carbon::parse($response->json('received_at'));
        $serverTime = Carbon::parse($response->json('server_time'));

        $this->assertTrue($serverTime->isAfter(now()->subMinute()));
    }

    public function test_an_identical_retry_returns_200_and_does_not_duplicate_the_row(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );
        $payload = $this->validPayload();

        $first = $this->postReading($device, $payload)->assertCreated();
        $id = $first->json('id');

        $retry = $this->postReading($device, $payload);

        $retry->assertOk()
            ->assertJsonPath('id', $id);

        $this->assertSame(1, Reading::count());
    }

    public function test_the_same_instant_with_different_values_returns_409(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );
        $payload = $this->validPayload();

        $first = $this->postReading($device, $payload)->assertCreated();
        $id = $first->json('id');

        $payload['co2'] = 950;

        $this->postReading($device, $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A reading at this exact instant already exists with different values.')
            ->assertJsonPath('reading.id', $id)
            ->assertJsonPath('reading.co2', 850);

        $this->assertSame(1, Reading::count());
    }

    public function test_a_single_sensor_device_stores_only_its_declared_measurement(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        unset($payload['co2']);

        $this->postReading($device, $payload)
            ->assertCreated()
            ->assertJsonPath('co2', null)
            ->assertJsonPath('temperature', 21.4);

        $this->assertDatabaseHas('readings', [
            'device_id' => $device->id,
            'co2' => null,
            'temperature' => 21.40,
            'humidity' => 61.50,
        ]);
    }

    public function test_a_mh_z19_only_device_stores_only_co2(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Co2);

        $payload = $this->validPayload();
        unset($payload['temperature'], $payload['humidity']);

        $this->postReading($device, $payload)
            ->assertCreated()
            ->assertJsonPath('co2', 850)
            ->assertJsonPath('temperature', null)
            ->assertJsonPath('humidity', null);
    }

    public function test_sensors_going_silent_store_null_explicitly(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $payload = $this->validPayload();
        $payload['co2'] = null;

        $this->postReading($device, $payload)
            ->assertCreated()
            ->assertJsonPath('co2', null);
    }

    public function test_the_same_instant_from_another_device_stores_normally(): void
    {
        $a = $this->deviceWith(ThresholdParameter::Co2);
        $b = $this->deviceWith(ThresholdParameter::Co2);
        $payload = $this->validPayload();
        unset($payload['temperature'], $payload['humidity']);

        $this->postReading($a, $payload)->assertCreated();

        $this->postReading($b, $payload)->assertCreated();

        $this->assertSame(2, Reading::count());
    }

    public function test_missing_token_is_rejected_before_validation(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Co2);

        $this->postJson('/api/readings', $this->validPayload())
            ->assertStatus(401);
    }

    public function test_a_validation_failure_still_marks_the_device_seen(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );
        $device->last_seen_at = now()->subDay();
        $device->save();

        $payload = $this->validPayload();
        $payload['measured_at'] = now()->addMinutes(10)->toIso8601String();

        $this->postReading($device, $payload)->assertStatus(422);

        $this->assertTrue(
            $device->fresh()->last_seen_at->isAfter(now()->subMinute()),
        );
    }
}
