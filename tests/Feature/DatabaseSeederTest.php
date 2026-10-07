<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The seeded fleet is only useful if device_sensors and readings agree.
 *
 * These tests pin that agreement: every reading must stay inside the
 * parameters its device declares, and the fleet must actually mix sensor
 * shapes so the null-capability design is exercised by real data.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_device_declares_at_least_one_parameter(): void
    {
        $this->seed();

        $withoutSensors = DB::table('devices')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('device_sensors')
                    ->whereColumn('devices.id', 'device_sensors.device_id');
            })
            ->count();

        $this->assertSame(0, $withoutSensors, 'A seeded device declares no sensors.');
    }

    public function test_no_reading_reports_a_parameter_its_device_does_not_carry(): void
    {
        $this->seed();

        foreach (ThresholdParameter::cases() as $parameter) {
            $undeclared = DB::table('readings')
                ->whereNotNull($parameter->value)
                ->whereNotIn('device_id', function ($query) use ($parameter) {
                    $query->select('device_id')
                        ->from('device_sensors')
                        ->where('parameter', $parameter->value);
                })
                ->count();

            $this->assertSame(
                0,
                $undeclared,
                "Readings report {$parameter->value} on a device that does not declare it.",
            );
        }
    }

    public function test_the_fleet_mixed_sensor_shapes_show_up_in_the_seeded_data(): void
    {
        $this->seed();

        $profiles = Device::with('sensors')->get()
            ->mapWithKeys(fn (Device $device) => [
                $device->name => $device->sensors->pluck('parameter')
                    ->map(fn (ThresholdParameter $parameter) => $parameter->value)
                    ->sort()
                    ->values()
                    ->all(),
            ]);

        // Two dual-sensor nodes, two DHT22-only nodes, one MH-Z19C-only node.
        $this->assertSame(['co2', 'humidity', 'temperature'], $profiles['Monstera Sensor']);
        $this->assertSame(['humidity', 'temperature'], $profiles['Cactus Sensor']);
        $this->assertSame(['humidity', 'temperature'], $profiles['Fiddle Leaf Fig Sensor']);
        $this->assertSame(['co2'], $profiles['Calathea Sensor']);
        $this->assertSame(['co2', 'humidity', 'temperature'], $profiles['Boston Fern Sensor']);

        // The shape has to be visible in readings, not just in the table.
        $cactus = Device::query()->where('name', 'Cactus Sensor')->firstOrFail();
        $this->assertSame(0, $cactus->readings()->whereNotNull('co2')->count(), 'A DHT22-only node reports co2.');
        $this->assertGreaterThan(1000, $cactus->readings()->count());

        $calathea = Device::query()->where('name', 'Calathea Sensor')->firstOrFail();
        $this->assertSame(0, $calathea->readings()->whereNotNull('temperature')->count(), 'An MH-Z19C-only node reports temperature.');
        $this->assertSame(0, $calathea->readings()->whereNotNull('humidity')->count(), 'An MH-Z19C-only node reports humidity.');
        $this->assertGreaterThan(0, $calathea->readings()->whereNotNull('co2')->count());
    }

    public function test_incident_readings_stay_within_the_device_sensors(): void
    {
        $this->seed();

        $this->assertSame(3, Alert::count(), 'The seeder replays three incidents.');

        // Fiddle Leaf Fig is DHT22-only, so even its incident reading must
        // not carry a co2 value.
        $fig = Device::query()->where('name', 'Fiddle Leaf Fig Sensor')->firstOrFail();

        $temperatureIncident = Alert::query()
            ->where('device_id', $fig->id)
            ->where('parameter', ThresholdParameter::Temperature)
            ->firstOrFail()
            ->reading;

        $this->assertNull($temperatureIncident->co2, 'An incident reading reports a parameter the device does not carry.');
        $this->assertEquals(33.4, (float) $temperatureIncident->temperature);

        // A dual-sensor node keeps all three values in its incident.
        $monstera = Device::query()->where('name', 'Monstera Sensor')->firstOrFail();

        $co2Incident = Alert::query()
            ->where('device_id', $monstera->id)
            ->where('parameter', ThresholdParameter::Co2)
            ->firstOrFail()
            ->reading;

        $this->assertEquals(1640, (float) $co2Incident->co2);
        $this->assertNotNull($co2Incident->temperature);
        $this->assertNotNull($co2Incident->humidity);
    }
}
