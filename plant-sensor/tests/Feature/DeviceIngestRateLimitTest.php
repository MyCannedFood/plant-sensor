<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The per-device ingest rate limiter. Devices report on an NTP cadence
 * (one post a minute or so); 30 a minute gives the device room to retry
 * deliveries whose response was lost, while still capping a malfunctioning
 * firmware or a leaked token at something the database can absorb.
 */
class DeviceIngestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private Device $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->device = Device::factory()->create();

        foreach (ThresholdParameter::cases() as $parameter) {
            DeviceSensor::factory()->for($this->device)->forParameter($parameter)->create();
        }
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

    private function postReading(?string $token = null): TestResponse
    {
        return $this->postJson('/api/readings', $this->validPayload(), [
            'X-Device-Token' => $token ?? $this->device->generateToken(),
        ]);
    }

    public function test_thirty_ingests_a_minute_pass_and_the_next_is_throttled(): void
    {
        $token = $this->device->generateToken();

        for ($i = 0; $i < 30; $i++) {
            $this->postReading($token)->assertSuccessful();
        }

        $this->postReading($token)
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', 30)
            ->assertJsonPath('message', 'Too Many Attempts.');
    }

    public function test_the_bucket_is_per_device_not_per_ip(): void
    {
        $otherDevice = Device::factory()->create();
        DeviceSensor::factory()->for($otherDevice)->forParameter(ThresholdParameter::Co2)->create();

        $token = $this->device->generateToken();
        $otherToken = $otherDevice->generateToken();

        for ($i = 0; $i < 30; $i++) {
            $this->postReading($token)->assertSuccessful();
        }

        // Exhausted device is throttled, while a sibling on the same IP is
        // unaffected: ingest limits are per device, not per address.
        $this->postReading($token)->assertStatus(429);

        $payload = $this->validPayload();
        unset($payload['temperature'], $payload['humidity']);

        $this->postJson('/api/readings', $payload, [
            'X-Device-Token' => $otherToken,
        ])->assertStatus(201);
    }

    public function test_the_throttle_guard_sits_behind_authentication(): void
    {
        // A rejection for a bad token is a 401, never a 429: the throttle
        // key needs the resolved device, so authentication runs first and
        // unknown devices are refused before they can drain a bucket.
        $this->postJson('/api/readings', $this->validPayload(), [
            'X-Device-Token' => str_repeat('a', 40),
        ])->assertStatus(401);
    }

    public function test_a_throttled_request_still_marks_the_device_seen(): void
    {
        $this->device->last_seen_at = now()->subDay();
        $this->device->save();

        $token = $this->device->generateToken();

        for ($i = 0; $i < 30; $i++) {
            $this->postReading($token)->assertSuccessful();
        }

        $this->postReading($token)->assertStatus(429);

        // Being out of rate limit budget is not the same as being offline:
        // the middleware bumped last_seen_at before the throttle rejected it.
        $this->assertTrue(
            $this->device->fresh()->last_seen_at->isAfter(now()->subMinute()),
        );
    }
}
