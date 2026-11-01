<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticateDeviceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The real /api/readings route lands with the controller in a later
     * step, so these tests probe the same alias on the same /api path
     * prefix, which is what makes errors render as JSON.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/device-auth-probe', fn (Request $request) => response()->json([
            'device_id' => $request->attributes->get('device')?->id,
        ]))->middleware('device.auth');
    }

    /**
     * Post to the probe with the given token, or with no header at all.
     */
    private function postProbe(?string $token): TestResponse
    {
        $headers = $token === null ? [] : ['X-Device-Token' => $token];

        return $this->postJson('/api/device-auth-probe', [], $headers);
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->postProbe(null)->assertStatus(401);
    }

    public function test_an_empty_token_is_rejected(): void
    {
        $this->postProbe('')->assertStatus(401);
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->postProbe('not-a-real-token')->assertStatus(401);
    }

    public function test_a_valid_token_resolves_the_device(): void
    {
        $device = Device::factory()->create();
        $token = $device->generateToken();

        $this->postProbe($token)
            ->assertStatus(200)
            ->assertJson(['device_id' => $device->id]);
    }

    public function test_a_token_resolves_only_its_own_device(): void
    {
        $first = Device::factory()->create();
        $second = Device::factory()->create();
        $token = $first->generateToken();

        $this->postProbe($token)
            ->assertStatus(200)
            ->assertJson(['device_id' => $first->id])
            ->assertJsonMissing(['device_id' => $second->id]);
    }

    public function test_an_inactive_device_is_refused(): void
    {
        $device = Device::factory()->inactive()->create();
        $token = $device->generateToken();

        $this->postProbe($token)->assertStatus(403);
    }

    public function test_a_device_in_maintenance_is_refused(): void
    {
        $device = Device::factory()->inMaintenance()->create();
        $token = $device->generateToken();

        $this->postProbe($token)->assertStatus(403);
    }

    public function test_last_seen_at_moves_on_a_successful_request(): void
    {
        $device = Device::factory()->create(['last_seen_at' => now()->subDay()]);
        $token = $device->generateToken();

        $this->postProbe($token)->assertStatus(200);

        $this->assertTrue(
            $device->fresh()->last_seen_at->greaterThan(now()->subHour()),
            'A device that successfully authenticated was not marked as seen.',
        );
    }

    public function test_a_refused_device_is_not_marked_as_seen(): void
    {
        $seenAt = now()->subDay();
        $device = Device::factory()->inactive()->create(['last_seen_at' => $seenAt]);
        $token = $device->generateToken();

        $this->postProbe($token)->assertStatus(403);

        $this->assertSame(
            $seenAt->getTimestamp(),
            $device->fresh()->last_seen_at->getTimestamp(),
            'A device we refused should not be freshened by the refusal.',
        );
    }

    public function test_errors_render_as_json_with_a_message(): void
    {
        $this->postProbe(null)
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }
}
