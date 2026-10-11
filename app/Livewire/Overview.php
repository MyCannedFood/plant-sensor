<?php

namespace App\Livewire;

use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Overview — Verdant')]
class Overview extends Component
{
    /**
     * Whether the inline "New plant" form is open.
     */
    public bool $showForm = false;

    /**
     * The new-plant form values.
     *
     * @var array{name: string, species: string, location: string, notes: string}
     */
    public array $plantForm = [
        'name' => '',
        'species' => '',
        'location' => '',
        'notes' => '',
    ];

    public function render()
    {
        return view('livewire.overview', [
            'rooms' => $this->roomCards(),
        ]);
    }

    public function showPlantForm(): void
    {
        $this->plantForm = ['name' => '', 'species' => '', 'location' => '', 'notes' => ''];
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancelPlantForm(): void
    {
        $this->showForm = false;
    }

    public function savePlant(): void
    {
        $this->validate([
            'plantForm.name' => ['required', 'string', 'max:60'],
            'plantForm.species' => ['nullable', 'string', 'max:120'],
            'plantForm.location' => ['nullable', 'string', 'max:80'],
            'plantForm.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'plantForm.name.required' => 'Give this plant a name.',
            'plantForm.name.max' => 'Keep the name under 60 characters.',
        ]);

        Plant::query()->create([
            'user_id' => $this->currentUser()->id,
            'name' => trim($this->plantForm['name']),
            'species' => $this->blank($this->plantForm['species']),
            'location' => $this->blank($this->plantForm['location']),
            'notes' => $this->blank($this->plantForm['notes']),
        ]);

        $this->showForm = false;
        $this->plantForm = ['name' => '', 'species' => '', 'location' => '', 'notes' => ''];
        $this->dispatch('plant-saved');
    }

    /**
     * The plant owner. Authentication does not exist yet, so the dashboard
     * hands ownership to an authenticated user when present, otherwise the
     * first user on record — creating one if the database is empty.
     */
    private function currentUser(): User
    {
        return auth()->user()
            ?? User::query()->orderBy('id')->first()
            ?? User::query()->firstOrCreate(
                ['email' => 'owner@verdant.local'],
                ['name' => 'Plant Owner', 'password' => 'password'],
            );
    }

    /**
     * Trim a form value, mapping a blank string back to null.
     */
    private function blank(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Build the overview card payload for every plant.
     *
     * @return list<array<string, mixed>>
     */
    public function roomCards(): array
    {
        return Plant::query()
            ->with([
                'devices' => fn ($query) => $query
                    ->with(['sensors', 'latestReading'])
                    ->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Plant $plant) => $this->cardFor($plant))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFor(Plant $plant): array
    {
        $device = $plant->devices->first();
        $previousReading = $device
            ? $device->readings()->orderByDesc('measured_at')->skip(1)->first()
            : null;

        return [
            'id' => $plant->id,
            'name' => $plant->name,
            'species' => $plant->species,
            'location' => $plant->location,
            'alert_count' => $device ? $this->activeAlertCount($device) : 0,
            'readings_today' => $device ? $this->readingsToday($device) : 0,
            'last_seen_text' => $device?->last_seen_at?->diffForHumans() ?? 'never',
            'alive' => $this->isAlive($device),
            'metrics' => $device
                ? $device->sensors->map(
                    fn ($sensor) => $this->metricCard($device, $sensor->parameter, $previousReading)
                )->values()->all()
                : [],
        ];
    }

    private function isAlive(?Device $device): bool
    {
        return $device !== null
            && $device->last_seen_at !== null
            && $device->last_seen_at->gt(now()->subMinutes(5));
    }

    private function activeAlertCount(Device $device): int
    {
        return Alert::query()
            ->where('device_id', $device->id)
            ->active()
            ->count();
    }

    private function readingsToday(Device $device): int
    {
        return $device->readings()
            ->whereDate('measured_at', today())
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function metricCard(Device $device, ThresholdParameter $parameter, ?Reading $previous = null): array
    {
        $latest = $device->latestReading;
        $column = $parameter->readingColumn();

        $value = $latest?->getAttribute($column);
        $status = $device->statusOf($parameter, $value);

        $previousValue = $previous?->getAttribute($column);

        if ($value === null) {
            $display = '—';
            $trend = 'flat';
        } else {
            $comparison = $value <=> $previousValue;

            $display = $this->format((float) $value, $parameter);
            $trend = $previousValue === null
                ? 'flat'
                : ($comparison > 0 ? 'up' : ($comparison < 0 ? 'down' : 'flat'));
        }

        return [
            'label' => $parameter->label(),
            'unit' => $parameter->unit(),
            'display' => $display,
            'status' => $status,
            'trend' => $trend,
        ];
    }

    private function format(float $value, ThresholdParameter $parameter): string
    {
        return number_format(
            $value,
            $parameter === ThresholdParameter::Temperature ? 1 : 0,
        );
    }
}
