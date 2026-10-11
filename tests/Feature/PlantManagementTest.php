<?php

namespace Tests\Feature;

use App\Livewire\Overview;
use App\Livewire\PlantMonitor;
use App\Models\Device;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    public function test_overview_shows_the_add_plant_affordance(): void
    {
        $this->get('/')->assertOk()->assertSee('Add plant');
    }

    public function test_overview_empty_state_invites_adding_a_plant(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('No plants yet')
            ->assertSee('Add your first plant');
    }

    public function test_a_plant_can_be_created_from_the_overview_form(): void
    {
        $this->makeUser();

        Livewire::test(Overview::class)
            ->assertOk()
            ->call('showPlantForm')
            ->set('plantForm.name', 'Peace Lily')
            ->set('plantForm.species', 'Spathiphyllum wallisii')
            ->set('plantForm.location', 'Office')
            ->call('savePlant')
            ->assertHasNoErrors()
            ->assertDispatched('plant-saved');

        $this->assertDatabaseHas('plants', [
            'name' => 'Peace Lily',
            'species' => 'Spathiphyllum wallisii',
            'location' => 'Office',
        ]);
    }

    public function test_a_plant_requires_a_name(): void
    {
        Livewire::test(Overview::class)
            ->call('showPlantForm')
            ->set('plantForm.name', '')
            ->call('savePlant')
            ->assertHasErrors('plantForm.name');

        $this->assertDatabaseCount('plants', 0);
    }

    public function test_plant_details_can_be_edited_from_the_monitor(): void
    {
        $user = $this->makeUser();
        $plant = Plant::factory()->for($user)->create(['name' => 'Old Name']);

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->assertOk()
            ->call('startEdit')
            ->set('plantDetails.name', 'New Name')
            ->set('plantDetails.notes', 'Water twice a week')
            ->call('savePlantDetails')
            ->assertHasNoErrors()
            ->assertDispatched('plant-saved');

        $this->assertDatabaseHas('plants', [
            'id' => $plant->id,
            'name' => 'New Name',
            'notes' => 'Water twice a week',
        ]);
    }

    public function test_a_plant_with_no_device_survives_the_monitor(): void
    {
        $user = $this->makeUser();
        $plant = Plant::factory()->for($user)->create(['name' => 'Empty Room']);

        $this->get('/plants/'.$plant->id)
            ->assertOk()
            ->assertSee('Empty Room')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }

    public function test_deleting_a_plant_requires_the_confirmation_state_first(): void
    {
        $user = $this->makeUser();
        $plant = Plant::factory()->for($user)->create();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->call('requestDelete')
            ->assertSet('confirmingDelete', true);
    }

    public function test_deleting_a_plant_removes_it_and_redirects(): void
    {
        $user = $this->makeUser();
        $plant = Plant::factory()->for($user)->create(['name' => 'Monstera Room']);
        $device = Device::factory()->for($plant)->create();
        Reading::factory()->for($device)->count(3)->sequence(
            ['measured_at' => now()->subMinutes(10)],
            ['measured_at' => now()->subMinutes(20)],
            ['measured_at' => now()->subMinutes(30)],
        )->create();

        Livewire::test(PlantMonitor::class, ['plant' => $plant])
            ->call('destroyPlant');

        $this->assertDatabaseMissing('plants', ['id' => $plant->id]);
        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('readings', ['device_id' => $device->id]);
    }
}
