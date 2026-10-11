<?php

namespace Tests\Feature;

use App\Enums\ThresholdParameter;
use App\Livewire\AlertHistory;
use App\Livewire\PlantMonitor;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a typical scenario: one plant, one node reporting all three
     * parameters, a short reading history and one unresolved alert.
     */
    private function makeScenario(): array
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->for($user)->create(['name' => 'Monstera Room']);
        $device = Device::factory()->for($plant)->create();

        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Co2)->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Temperature)->create();
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Humidity)->create();

        Reading::factory()->for($device)->create(['measured_at' => now()->subMinutes(10)]);
        Reading::factory()->for($device)->create(['measured_at' => now()->subMinutes(20)]);

        $latest = Reading::factory()->for($device)->create(['measured_at' => now()]);
        Alert::factory()->for($latest)->create([
            'parameter' => ThresholdParameter::Co2,
        ]);

        return [$plant, $device];
    }

    public function test_overview_lists_plants_with_metric_chips(): void
    {
        [$plant] = $this->makeScenario();

        $this->get('/')
            ->assertOk()
            ->assertSee('Verdant')
            ->assertSee('Monstera Room')
            ->assertSee('CO₂')
            ->assertSee('Temperature')
            ->assertSee('Humidity');
    }

    public function test_plant_monitor_renders_readings_thresholds_and_alerts(): void
    {
        [$plant, $device] = $this->makeScenario();

        $this->get('/plants/'.$plant->id)
            ->assertOk()
            ->assertSee('Monstera Room')
            ->assertSee('MH-Z19C')
            ->assertSee('DHT22')
            ->assertSee('Threshold settings')
            ->assertSee('Recent alerts');
    }

    public function test_plant_monitor_only_shows_metrics_the_device_physically_carries(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->for($user)->create();
        $device = Device::factory()->for($plant)->create();

        // A pure MH-Z19C node: CO₂ only, no DHT22 pair.
        DeviceSensor::factory()->for($device)->forParameter(ThresholdParameter::Co2)->create();
        Reading::factory()->for($device)->create();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->assertOk()
            ->assertSet('metric', 'co2')
            ->assertSee('CO₂')
            ->assertDontSee('Temperature');
    }

    public function test_metric_and_range_switchers_push_a_fresh_chart_config(): void
    {
        [$plant] = $this->makeScenario();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->assertOk()
            ->set('metric', 'temperature')
            ->assertSet('metric', 'temperature')
            ->assertDispatched('trend-chart')
            ->set('range', '7d')
            ->assertDispatched('trend-chart');
    }

    public function test_thresholds_can_be_saved_through_the_form(): void
    {
        [$plant, $device] = $this->makeScenario();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->assertOk()
            ->set('thresholdForm.co2.min', '350')
            ->set('thresholdForm.co2.max', '1200')
            ->call('saveThresholds')
            ->assertHasNoErrors()
            ->assertDispatched('thresholds-saved')
            ->assertDispatched('trend-chart');

        $this->assertDatabaseHas('thresholds', [
            'device_id' => $device->id,
            'parameter' => 'co2',
            'min_value' => 350,
            'max_value' => 1200,
        ]);
    }

    public function test_invalid_threshold_values_are_rejected(): void
    {
        [$plant] = $this->makeScenario();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->set('thresholdForm.co2.max', 'not-a-number')
            ->call('saveThresholds')
            ->assertHasErrors('thresholdForm.co2.max');
    }

    public function test_alert_history_lists_events_across_rooms(): void
    {
        [$plant] = $this->makeScenario();

        $this->get('/alerts')
            ->assertOk()
            ->assertSee('Alert history')
            ->assertSee('Monstera Room');
    }

    public function test_alert_filter_tabs_change_the_query(): void
    {
        $user = User::factory()->create();
        $plant = Plant::factory()->for($user)->create(['name' => 'Monstera Room']);
        $device = Device::factory()->for($plant)->create();
        $reading = Reading::factory()->for($device)->create();

        Alert::factory()->for($reading)->create(['triggered_at' => now()]);

        Livewire::test(AlertHistory::class)
            ->assertOk()
            ->assertSee('Monstera Room')
            ->call('setFilter', 'resolved')
            ->assertDontSee('Monstera Room');
    }
}
