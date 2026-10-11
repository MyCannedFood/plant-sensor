<?php

namespace App\Livewire;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Plant;
use App\Models\Reading;
use App\Models\Threshold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Plant monitor — Verdant')]
class PlantMonitor extends Component
{
    public Plant $plant;

    public string $metric = 'co2';

    public string $range = '24h';

    /**
     * Threshold form values, keyed by parameter value.
     *
     * @var array<string, array{min: string, max: string}>
     */
    public array $thresholdForm = [];

    /**
     * Timestamp (seconds) of the newest reading the chart was built from, used
     * to avoid useless chart payloads on every poll tick.
     */
    public ?int $lastPoint = null;

    /**
     * Whether the inline "Edit plant" form is open.
     */
    public bool $editing = false;

    /**
     * Whether the destructive "Delete plant" confirmation is open.
     */
    public bool $confirmingDelete = false;

    /**
     * What the destructive confirmation threatens to remove, computed when the
     * confirmation opens so the render at poll time runs no extra queries.
     *
     * @var array{devices: int, readings: int, alerts: int}
     */
    public array $deleteSummary = ['devices' => 0, 'readings' => 0, 'alerts' => 0];

    /**
     * The edit form values for the plant's own details.
     *
     * @var array{name: string, species: string, location: string, notes: string}
     */
    public array $plantDetails = [
        'name' => '',
        'species' => '',
        'location' => '',
        'notes' => '',
    ];

    public function mount(Plant $plant): void
    {
        $this->plant = $plant->load('devices.sensors');

        $device = $this->device();
        $this->metric = $device?->sensors->first()?->parameter->value ?? 'co2';

        foreach ($this->metrics() as $parameter) {
            [$min, $max] = $device->thresholdsFor($parameter);

            $this->thresholdForm[$parameter->value] = [
                'min' => $min === null ? '' : (string) $min,
                'max' => $max === null ? '' : (string) $max,
            ];
        }

        // Record the newest reading the initial chart was built from so the
        // first poll tick does not needlessly re-push the whole series.
        $this->lastPoint = $device?->latestReading?->measured_at?->getTimestamp();

        $this->plantDetails = [
            'name' => $plant->name,
            'species' => (string) $plant->species,
            'location' => (string) $plant->location,
            'notes' => (string) $plant->notes,
        ];
    }

    public function startEdit(): void
    {
        $this->plantDetails = [
            'name' => $this->plant->name,
            'species' => (string) $this->plant->species,
            'location' => (string) $this->plant->location,
            'notes' => (string) $this->plant->notes,
        ];
        $this->editing = true;
        $this->confirmingDelete = false;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editing = false;
    }

    public function savePlantDetails(): void
    {
        $this->validate([
            'plantDetails.name' => ['required', 'string', 'max:60'],
            'plantDetails.species' => ['nullable', 'string', 'max:120'],
            'plantDetails.location' => ['nullable', 'string', 'max:80'],
            'plantDetails.notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'plantDetails.name.required' => 'Give this plant a name.',
        ]);

        $this->plant->update([
            'name' => trim($this->plantDetails['name']),
            'species' => $this->blank($this->plantDetails['species']),
            'location' => $this->blank($this->plantDetails['location']),
            'notes' => $this->blank($this->plantDetails['notes']),
        ]);

        $this->editing = false;
        $this->dispatch('plant-saved');
    }

    public function requestDelete(): void
    {
        $this->confirmingDelete = true;
        $this->editing = false;

        $deviceIds = $this->plant->devices()->pluck('id');

        $this->deleteSummary = [
            'devices' => $deviceIds->count(),
            'readings' => Reading::query()->whereIn('device_id', $deviceIds)->count(),
            'alerts' => Alert::query()->whereIn('device_id', $deviceIds)->count(),
        ];
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function destroyPlant(): void
    {
        $name = $this->plant->name;
        $this->plant->delete();

        session()->flash('notice', "{$name} was deleted.");

        $this->redirectRoute('overview');
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
     * The primary node of the room. A plant may own several nodes; for now
     * the monitor focuses on the first one and lists the rest in the panel.
     */
    public function device(): ?Device
    {
        return $this->plant->devices->first();
    }

    /**
     * @return Collection<int, ThresholdParameter>
     */
    public function metrics(): Collection
    {
        return $this->device()?->sensors->pluck('parameter') ?? collect();
    }

    public function setMetric(string $metric): void
    {
        if ($this->metrics()->pluck('value')->contains($metric)) {
            $this->metric = $metric;
        }
    }

    public function setRange(string $range): void
    {
        $this->range = in_array($range, ['1h', '24h', '7d'], true) ? $range : '24h';
    }

    public function updatedMetric(): void
    {
        $this->dispatchChart();
    }

    public function updatedRange(): void
    {
        $this->dispatchChart();
    }

    /**
     * Called by wire:poll so last-seen and stat cards stay live. The chart is
     * only pushed to the browser when a new reading has actually arrived.
     */
    public function refresh(): void
    {
        $latest = $this->device()?->latestReading;
        $timestamp = $latest?->measured_at?->getTimestamp(); // seconds

        if ($timestamp !== null && $timestamp !== $this->lastPoint) {
            $this->lastPoint = $timestamp;
            $this->dispatchChart();
        }
    }

    /**
     * Push the current chart configuration to the browser so the ApexCharts
     * instance can be updated without morphing (the container is wire:ignore).
     */
    private function dispatchChart(): void
    {
        $this->dispatch('trend-chart', config: $this->chartConfig());
    }

    public function saveThresholds(): void
    {
        // Normalise empty inputs to null (no request middleware touches
        // Livewire state, so '' has to be converted here).
        foreach ($this->metrics() as $parameter) {
            $key = $parameter->value;

            foreach (['min', 'max'] as $bound) {
                $value = trim((string) ($this->thresholdForm[$key][$bound] ?? ''));
                $this->thresholdForm[$key][$bound] = $value === '' ? null : $value;
            }
        }

        $this->validate([
            'thresholdForm.*.min' => ['nullable', 'numeric'],
            'thresholdForm.*.max' => ['nullable', 'numeric'],
        ]);

        $device = $this->device();

        foreach ($this->metrics() as $parameter) {
            $values = $this->thresholdForm[$parameter->value];

            Threshold::updateOrCreate(
                ['device_id' => $device->id, 'parameter' => $parameter],
                [
                    'min_value' => $values['min'] === null ? null : (float) $values['min'],
                    'max_value' => $values['max'] === null ? null : (float) $values['max'],
                ],
            );
        }

        $this->dispatch('trend-chart', config: $this->chartConfig());
        $this->dispatch('thresholds-saved');
    }

    /**
     * Stat cards for the metrics the device physically carries.
     *
     * @return list<array<string, mixed>>
     */
    public function metricCards(): array
    {
        $device = $this->device();

        if ($device === null) {
            return [];
        }

        return $this->metrics()
            ->map(function (ThresholdParameter $parameter) use ($device) {
                $column = $parameter->readingColumn();
                $value = $device->latestReading?->getAttribute($column);
                $status = $device->statusOf($parameter, $value);

                [$min, $max] = $device->thresholdsFor($parameter);

                return [
                    'label' => $parameter->label(),
                    'unit' => $parameter->unit(),
                    'display' => $value === null ? '—' : $this->format((float) $value, $parameter),
                    'status' => $status,
                    'status_label' => strtoupper($status),
                    'band' => $min === null || $max === null
                        ? 'not set'
                        : $this->format($min, $parameter).'–'.$this->format($max, $parameter),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string, short: string, unit: string, active: bool, step: string}>
     */
    public function metricOptions(): array
    {
        return $this->metrics()
            ->map(fn (ThresholdParameter $parameter) => [
                'value' => $parameter->value,
                'label' => $parameter->label(),
                'short' => $parameter->short(),
                'unit' => $parameter->unit(),
                'active' => $this->metric === $parameter->value,
                'step' => $parameter === ThresholdParameter::Temperature ? '0.5' : '5',
            ])
            ->values()
            ->all();
    }

    /**
     * ApexCharts configuration embedded into the view as JSON. The chart JS
     * re-renders whenever this attribute changes.
     *
     * @return array<string, mixed>
     */
    public function chartConfig(): array
    {
        $device = $this->device();
        $parameter = ThresholdParameter::from($this->metric);

        $points = $device === null ? [] : $this->series($device, $parameter);

        [$min, $max] = $device?->thresholdsFor($parameter) ?? [null, null];
        $last = $points === [] ? null : end($points);

        $unit = $parameter->unit();

        return [
            'chart' => [
                'type' => 'area',
                'height' => 270,
                'toolbar' => ['show' => false],
                'zoom' => ['enabled' => true],
                'fontFamily' => 'inherit',
            ],
            'series' => [[
                'name' => $parameter->label(),
                'data' => $points,
            ]],
            'dataLabels' => ['enabled' => false],
            'stroke' => ['curve' => 'smooth', 'width' => 2.4, 'colors' => ['#1D9E6B']],
            'fill' => ['type' => 'gradient', 'gradient' => [
                'shadeIntensity' => 1, 'opacityFrom' => 0.22, 'opacityTo' => 0, 'stops' => [0, 92],
            ]],
            'colors' => ['#1D9E6B'],
            'markers' => ['size' => 0],
            'grid' => ['borderColor' => '#E6EBE6', 'strokeDashArray' => 4],
            'tooltip' => ['x' => ['format' => 'HH:mm · d MMM']],
            'xaxis' => [
                'type' => 'datetime',
                'labels' => ['style' => ['colors' => '#87948A', 'fontSize' => '10.5px', 'fontFamily' => 'IBM Plex Mono']],
            ],
            'yaxis' => [
                'labels' => ['style' => ['colors' => '#87948A', 'fontSize' => '10.5px', 'fontFamily' => 'IBM Plex Mono']],
            ],
            'annotations' => ['yaxis' => $this->chartAnnotations($min, $max, $parameter, $last)],
            'legend' => ['show' => false],
        ];
    }

    /**
     * @param  array{x: int, y: float}|null  $last
     * @return list<array<string, mixed>>
     */
    private function chartAnnotations(?float $min, ?float $max, ThresholdParameter $parameter, ?array $last): array
    {
        $annotations = [];

        if ($min !== null && $max !== null) {
            $annotations[] = ['y' => $min, 'y2' => $max, 'fillColor' => '#1D9E6B', 'opacity' => 0.05];
            $annotations[] = [
                'y' => $max,
                'strokeDashArray' => 4,
                'borderColor' => '#C17D1C',
                'label' => [
                    'text' => "max {$this->format($max, $parameter)} {$parameter->unit()}",
                    'position' => 'right',
                    'offsetY' => -9,
                    'style' => ['fontSize' => '10px', 'fontFamily' => 'IBM Plex Mono', 'color' => '#C17D1C', 'background' => 'rgba(255,255,255,0.85)'],
                ],
            ];
            $annotations[] = [
                'y' => $min,
                'strokeDashArray' => 4,
                'borderColor' => '#87948A',
                'label' => [
                    'text' => "min {$this->format($min, $parameter)} {$parameter->unit()}",
                    'position' => 'right',
                    'offsetY' => 12,
                    'style' => ['fontSize' => '10px', 'fontFamily' => 'IBM Plex Mono', 'color' => '#87948A', 'background' => 'rgba(255,255,255,0.85)'],
                ],
            ];
        }

        if ($last !== null) {
            $annotations[] = [
                'x' => $last['x'],
                'borderColor' => '#1D9E6B',
                'borderWidth' => 1,
            ];
        }

        return $annotations;
    }

    /**
     * @return list<array{x: int, y: float}>
     */
    private function series(Device $device, ThresholdParameter $parameter): array
    {
        $column = $parameter->readingColumn();

        return Reading::query()
            ->where('device_id', $device->id)
            ->where('measured_at', '>=', $this->cutoff())
            ->whereNotNull($column)
            ->orderBy('measured_at')
            ->get([$column, 'measured_at'])
            ->map(fn (Reading $reading) => [
                'x' => $reading->measured_at->getTimestamp() * 1000,
                'y' => (float) $reading->getAttribute($column),
            ])
            ->values()
            ->all();
    }

    private function cutoff(): Carbon
    {
        return now()->subMinutes(match ($this->range) {
            '1h' => 60,
            '7d' => 7 * 24 * 60,
            default => 24 * 60,
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deviceRows(): array
    {
        return $this->plant->devices
            ->map(function (Device $device) {
                $online = $device->last_seen_at !== null && $device->last_seen_at->gt(now()->subMinutes(5));

                return [
                    'name' => $device->name,
                    'badges' => $device->sensors->map(
                        fn ($sensor) => $sensor->parameter === ThresholdParameter::Co2 ? 'MH-Z19C' : 'DHT22'
                    )->all(),
                    'online' => $online,
                    'status_word' => $online ? 'Online' : 'Asleep',
                    'last_seen' => $device->last_seen_at?->diffForHumans() ?? 'never',
                    'readings_today' => $this->readingsToday($device),
                ];
            })
            ->values()
            ->all();
    }

    private function readingsToday(Device $device): int
    {
        return $device->readings()
            ->whereDate('measured_at', today())
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentAlerts(): array
    {
        $device = $this->device();

        if ($device === null) {
            return [];
        }

        return Alert::query()
            ->where('device_id', $device->id)
            ->orderByDesc('triggered_at')
            ->limit(3)
            ->get()
            ->map(fn (Alert $alert) => $this->describe($alert))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Alert $alert): array
    {
        return [
            'severity_key' => $alert->resolved_at ? 'good' : ($alert->severity === AlertSeverity::Critical ? 'alert' : 'watch'),
            'time' => $alert->triggered_at->format('H:i'),
            'message' => $this->alertMessage($alert),
            'meta' => $alert->device?->name ?? 'Unknown node',
        ];
    }

    private function alertMessage(Alert $alert): string
    {
        $direction = $alert->direction === AlertDirection::AboveMaximum ? 'above' : 'below';

        return sprintf(
            '%s at %s %s moved %s the %s threshold',
            $alert->parameter->label(),
            $this->format((float) $alert->value, $alert->parameter),
            $alert->parameter->unit(),
            $direction,
            $alert->direction === AlertDirection::BelowMinimum ? 'minimum' : 'maximum',
        );
    }

    private function format(float $value, ThresholdParameter $parameter): string
    {
        $decimals = $parameter === ThresholdParameter::Temperature ? 1 : 0;

        return number_format($value, $decimals);
    }
}
