<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\Reading;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Locks in the invariants the Phase 1 migrations introduced.
 *
 * These tests guard the schema itself, not the API: any future migration that
 * quietly reverts nullability, the fractional humidity column, the per-device
 * uniqueness of a reading, or the capability table should fail here.
 */
class ReadingSchemaGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_without_a_co2_sensor_can_report_temp_and_humidity_only(): void
    {
        $device = Device::factory()->create();

        $reading = Reading::factory()->create([
            'device_id' => $device->id,
            'co2' => null,
        ]);

        $stored = Reading::findOrFail($reading->id);

        $this->assertNull($stored->co2);
        $this->assertNotNull($stored->temperature);
        $this->assertNotNull($stored->humidity);
    }

    public function test_a_device_with_only_a_co2_sensor_leaves_temp_and_humidity_null(): void
    {
        $device = Device::factory()->create();

        $reading = Reading::factory()->create([
            'device_id' => $device->id,
            'temperature' => null,
            'humidity' => null,
        ]);

        $stored = Reading::findOrFail($reading->id);

        $this->assertNotNull($stored->co2);
        $this->assertNull($stored->temperature);
        $this->assertNull($stored->humidity);
    }

    public function test_fractional_humidity_survives_the_round_trip(): void
    {
        $device = Device::factory()->create();

        $reading = Reading::factory()->create([
            'device_id' => $device->id,
            'humidity' => 67.5,
        ]);

        $stored = Reading::findOrFail($reading->id);

        // The DHT22 resolves 0.1 %RH, so a whole-number column would corrupt
        // what the hardware is entitled to report.
        $this->assertSame('67.50', (string) $stored->humidity);
        $this->assertSame(67.5, (float) DB::table('readings')->where('id', $reading->id)->value('humidity'));
    }

    public function test_two_readings_from_the_same_device_at_the_same_moment_are_refused(): void
    {
        $device = Device::factory()->create();

        Reading::factory()->create([
            'device_id' => $device->id,
            'measured_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Duplicate entry/');

        Reading::factory()->create([
            'device_id' => $device->id,
            'measured_at' => now(),
        ]);
    }

    public function test_the_same_moment_on_two_different_devices_is_allowed(): void
    {
        $first = Device::factory()->create();
        $second = Device::factory()->create();

        Reading::factory()->create(['device_id' => $first->id, 'measured_at' => now()]);
        Reading::factory()->create(['device_id' => $second->id, 'measured_at' => now()]);

        $this->assertSame(2, Reading::count());
    }

    public function test_readings_reject_a_device_that_does_not_exist(): void
    {
        // The unique index added in Phase 1 is also what backs the foreign key
        // after the plain index was dropped, so the constraint has to survive
        // the swap.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/constraint/i');

        Reading::factory()->create(['device_id' => 999999]);
    }

    public function test_received_at_is_not_null_and_a_datetime_column(): void
    {
        $device = Device::factory()->create();

        $this->assertSame('datetime', Schema::getColumnType('readings', 'received_at'));

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/cannot be null/i');

        DB::table('readings')->insert([
            'device_id' => $device->id,
            'measured_at' => now(),
            'received_at' => null,
            'co2' => 850,
        ]);
    }

    public function test_device_sensors_refuse_a_duplicate_parameter(): void
    {
        $device = Device::factory()->create();

        DB::table('device_sensors')->insert([
            'device_id' => $device->id,
            'parameter' => ThresholdParameter::Temperature,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Duplicate entry/');

        DB::table('device_sensors')->insert([
            'device_id' => $device->id,
            'parameter' => ThresholdParameter::Temperature,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_device_sensors_cascade_when_the_device_is_deleted(): void
    {
        $device = Device::factory()->create();

        DB::table('device_sensors')->insert([
            'device_id' => $device->id,
            'parameter' => ThresholdParameter::Co2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $device->delete();

        $this->assertSame(0, DB::table('device_sensors')->count());
    }

    public function test_device_sensors_accept_a_declared_parameter_once_per_device(): void
    {
        $first = Device::factory()->create();
        $second = Device::factory()->create();

        foreach ([$first, $second] as $device) {
            DB::table('device_sensors')->insert([
                'device_id' => $device->id,
                'parameter' => ThresholdParameter::Co2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame(2, DB::table('device_sensors')->count());
    }
}
