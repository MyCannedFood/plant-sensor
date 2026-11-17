<?php

namespace App\Http\Controllers;

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
use App\Http\Requests\StoreReadingRequest;
use App\Http\Resources\ReadingResource;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceSensor;
use App\Models\Reading;
use App\Models\Threshold;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReadingController extends Controller
{
    /**
     * Store a reading a device reports.
     *
     * createOrFirst turns the (device_id, measured_at) unique key into the
     * stuck-clock guard: a fresh instant inserts and returns 201; a retry
     * of an earlier post the device never saw the answer to lands on the
     * existing row — 200 when the values are identical, 409 when they
     * diverge (the device's clock is producing two snapshots for one
     * instant, so at least one of them is a lie).
     */
    public function store(StoreReadingRequest $request): JsonResponse
    {
        $device = $request->device();
        $validated = $request->validated();

        $reading = Reading::createOrFirst(
            [
                'device_id' => $device->id,
                // Carbon, not the raw ISO string: the collision lookup in
                // createOrFirst queries with these keys and the query grammar
                // formats DateTimeInterface the same way the column casts it,
                // so the retry finds the stored row instead of throwing.
                'measured_at' => Carbon::parse($validated['measured_at']),
            ],
            [
                'co2' => $validated['co2'] ?? null,
                'temperature' => $validated['temperature'] ?? null,
                'humidity' => $validated['humidity'] ?? null,
                'ph' => $validated['ph'] ?? null,
                'ec' => $validated['ec'] ?? null,
                'water_temperature' => $validated['water_temperature'] ?? null,
                'light' => $validated['light'] ?? null,
                'water_level' => $validated['water_level'] ?? null,
            ],
        );

        $isNew = $reading->wasRecentlyCreated;
        $isDuplicateMatch = $this->storedValuesMatch($reading, $validated);

        if ($isNew || $isDuplicateMatch) {
            $this->evaluateAlerts($device, $reading, $validated);
        }

        if ($isNew) {
            return (new ReadingResource($reading))->response()->setStatusCode(201);
        }

        if ($isDuplicateMatch) {
            return (new ReadingResource($reading))->response();
        }

        return response()->json([
            'message' => 'A reading at this exact instant already exists with different values.',
            'reading' => new ReadingResource($reading),
        ], 409);
    }

    /**
     * Get the sensors declared for the authenticated device.
     */
    public function sensors(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        $sensors = DeviceSensor::query()
            ->where('device_id', $device->id)
            ->get()
            ->map(fn ($sensor) => [
                'parameter' => $sensor->parameter->value,
                'label' => $sensor->parameter->label(),
                'unit' => $sensor->parameter->unit(),
                'short' => $sensor->parameter->short(),
                'decimals' => $sensor->parameter->decimals(),
            ])
            ->values();

        return response()->json([
            'device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'sensors' => $sensors,
        ]);
    }

    /**
     * Whether the retried payload matches what is already stored.
     *
     * Null-sensitive: an absent/null sensor must stay null (a sensor going
     * silent does not reuse the previous value). Numeric values agree when
     * they are equal to two decimal places, matching the decimal:2 columns.
     *
     * @param  array<string, mixed>  $validated
     */
    private function storedValuesMatch(Reading $reading, array $validated): bool
    {
        foreach (ThresholdParameter::cases() as $parameter) {
            $column = $parameter->readingColumn();
            $stored = $reading->getAttribute($column);
            $incoming = $validated[$column] ?? null;

            if ($stored === null || $incoming === null) {
                if ($stored !== $incoming) {
                    return false;
                }

                continue;
            }

            if (abs((float) $stored - (float) $incoming) >= 0.005) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a stored reading against thresholds and create or resolve
     * alerts for any breached parameters.
     *
     * @param  array<string, mixed>  $validated
     */
    private function evaluateAlerts(Device $device, Reading $reading, array $validated): void
    {
        foreach (ThresholdParameter::cases() as $parameter) {
            $column = $parameter->readingColumn();
            $value = $validated[$column] ?? null;

            if ($value === null) {
                continue;
            }

            $threshold = Threshold::query()
                ->applicableTo($device)
                ->where('parameter', $parameter)
                ->orderByRaw('device_id is null')
                ->first();

            $min = $threshold?->min_value !== null ? (float) $threshold->min_value : null;
            $max = $threshold?->max_value !== null ? (float) $threshold->max_value : null;

            if ($min === null || $max === null) {
                // No bounds configured — nothing to alert on.
                continue;
            }

            $breachDirection = null;
            if ($value < $min) {
                $breachDirection = AlertDirection::BelowMinimum->value;
            } elseif ($value > $max) {
                $breachDirection = AlertDirection::AboveMaximum->value;
            }

            $existing = Alert::query()
                ->where('device_id', $device->id)
                ->where('parameter', $parameter)
                ->whereNull('resolved_at')
                ->first();

            if ($breachDirection === null) {
                // Value is back within bounds — resolve any open alert.
                if ($existing) {
                    $existing->update(['resolved_at' => $reading->measured_at]);
                }
                continue;
            }

            // Severity: severe breach (beyond half the range width) = critical.
            $rangeWidth = $max - $min;
            $breachAmount = $breachDirection === AlertDirection::BelowMinimum->value
                ? $min - (float) $value
                : (float) $value - $max;
            $severity = $rangeWidth > 0 && ($breachAmount / $rangeWidth) > 0.5
                ? AlertSeverity::Critical->value
                : AlertSeverity::Warning->value;

            if ($existing) {
                if ($existing->direction === $breachDirection) {
                    // Same direction already alerting — no change needed.
                    continue;
                }
                // Direction flipped — resolve the old alert, create a new one.
                $existing->update(['resolved_at' => $reading->measured_at]);
            }

            Alert::query()->create([
                'reading_id' => $reading->id,
                'device_id' => $device->id,
                'threshold_id' => $threshold?->id,
                'parameter' => $parameter,
                'direction' => $breachDirection,
                'severity' => $severity,
                'value' => $value,
                'triggered_at' => $reading->measured_at,
            ]);
        }
    }
}
