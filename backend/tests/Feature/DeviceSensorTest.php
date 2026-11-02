<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\DeviceSensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceSensorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_factory_can_declare_a_parameter_on_a_device(): void
    {
        $sensor = DeviceSensor::factory()
            ->forParameter(ThresholdParameter::Co2)
            ->create();

        $this->assertSame(ThresholdParameter::Co2, $sensor->parameter);
        $this->assertSame($sensor->device->id, $sensor->device_id);
    }

    public function test_a_device_lists_exactly_the_parameters_it_declared(): void
    {
        $device = Device::factory()->create();

        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Temperature)->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Humidity)->create();

        $this->assertCount(2, $device->sensors);
        $this->assertTrue($device->declares(ThresholdParameter::Temperature));
        $this->assertTrue($device->declares(ThresholdParameter::Humidity));
        $this->assertFalse($device->declares(ThresholdParameter::Co2));
    }

    public function test_declares_answers_from_a_query_when_the_relation_is_not_loaded(): void
    {
        $device = Device::factory()->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Co2)->create();

        $fresh = Device::findOrFail($device->id);

        $this->assertFalse($fresh->relationLoaded('sensors'));
        $this->assertTrue($fresh->declares(ThresholdParameter::Co2));
        $this->assertFalse($fresh->declares(ThresholdParameter::Humidity));
    }

    public function test_declares_answers_from_the_loaded_relation_when_available(): void
    {
        $device = Device::factory()->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Co2)->create();

        $loaded = Device::with('sensors')->findOrFail($device->id);

        $this->assertTrue($loaded->relationLoaded('sensors'));
        $this->assertTrue($loaded->declares(ThresholdParameter::Co2));
        $this->assertFalse($loaded->declares(ThresholdParameter::Humidity));
    }
}
