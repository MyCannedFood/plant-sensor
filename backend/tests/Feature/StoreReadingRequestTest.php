<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Http\Requests\StoreReadingRequest;
use App\Models\Device;
use App\Models\DeviceSensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The validation gate for the ingest endpoint.
 *
 * The real route arrives with the controller in a later step, so these
 * tests exercise the FormRequest through a probe route that runs the same
 * alias and the same /api prefix, which is what makes failures render as
 * 422 JSON.
 */
class StoreReadingRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/api/reading-probe', fn (StoreReadingRequest $request) => response()->json([
            'validated' => $request->validated(),
        ]))->middleware('device.auth');
    }

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
        return $this->postJson('/api/reading-probe', $payload, [
            'X-Device-Token' => $device->generateToken(),
        ]);
    }

    public function test_a_complete_payload_from_a_dual_sensor_device_is_accepted(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $this->postReading($device, $this->validPayload())
            ->assertStatus(200)
            ->assertJsonPath('validated.co2', 850)
            ->assertJsonPath('validated.temperature', 21.4)
            ->assertJsonPath('validated.humidity', 61.5);
    }

    public function test_a_partial_payload_from_a_single_sensor_device_is_accepted(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        unset($payload['co2']);

        $this->postReading($device, $payload)->assertStatus(200);
    }

    public function test_an_explicit_null_is_accepted_for_a_declared_parameter(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $payload = $this->validPayload();
        $payload['co2'] = null;

        $this->postReading($device, $payload)
            ->assertStatus(200)
            ->assertJsonPath('validated.co2', null);
    }

    public function test_an_explicit_null_is_accepted_for_an_undeclared_parameter(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        $payload['co2'] = null;

        $this->postReading($device, $payload)->assertStatus(200);
    }

    public function test_a_non_null_value_for_an_undeclared_parameter_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $this->postReading($device, $this->validPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['co2']);
    }

    public function test_a_payload_without_any_measurement_is_rejected(): void
    {
        $device = $this->deviceWith(
            ThresholdParameter::Co2,
            ThresholdParameter::Temperature,
            ThresholdParameter::Humidity,
        );

        $payload = $this->validPayload();
        $payload['co2'] = null;
        $payload['temperature'] = null;
        $payload['humidity'] = null;

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['measurements']);
    }

    public function test_measured_at_is_required(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature);

        $payload = $this->validPayload();
        unset($payload['measured_at']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['measured_at']);
    }

    public function test_measured_at_beyond_five_minutes_in_the_future_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        $payload['measured_at'] = now()->addMinutes(10)->toIso8601String();
        unset($payload['co2']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['measured_at']);
    }

    public function test_measured_at_within_clock_drift_tolerance_is_accepted(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature, ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        $payload['measured_at'] = now()->addMinutes(2)->toIso8601String();
        unset($payload['co2']);

        $this->postReading($device, $payload)->assertStatus(200);
    }

    public function test_co2_above_the_column_bearing_range_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Co2);

        $payload = $this->validPayload();
        $payload['co2'] = 10001;
        unset($payload['temperature'], $payload['humidity']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['co2']);
    }

    public function test_co2_bounds_are_inclusive(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Co2);

        $payload = $this->validPayload();
        $payload['co2'] = 10000;
        unset($payload['temperature'], $payload['humidity']);

        $this->postReading($device, $payload)->assertStatus(200);
    }

    public function test_co2_must_be_a_whole_number(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Co2);

        $payload = $this->validPayload();
        $payload['co2'] = 850.5;
        unset($payload['temperature'], $payload['humidity']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['co2']);
    }

    public function test_temperature_outside_the_datasheet_range_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Temperature);

        foreach ([81, -41] as $temperature) {
            $payload = $this->validPayload();
            $payload['temperature'] = $temperature;
            unset($payload['co2'], $payload['humidity']);

            $this->postReading($device, $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['temperature']);
        }
    }

    public function test_humidity_above_100_percent_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        $payload['humidity'] = 101;
        unset($payload['co2'], $payload['temperature']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['humidity']);
    }

    public function test_humidity_with_more_than_two_decimals_is_rejected(): void
    {
        $device = $this->deviceWith(ThresholdParameter::Humidity);

        $payload = $this->validPayload();
        $payload['humidity'] = 67.555;
        unset($payload['co2'], $payload['temperature']);

        $this->postReading($device, $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['humidity']);
    }
}
