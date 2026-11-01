<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ThresholdParameter;
use App\Http\Controllers\Controller;
use App\Models\Plant;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The trend chart data for a plant's primary device.
 *
 * Returns raw points plus the threshold band; how the chart looks (colours,
 * fonts, annotations) is entirely the frontend's business.
 */
class PlantReadingController extends Controller
{
    private const RANGES = ['1h' => 60, '24h' => 24 * 60, '7d' => 7 * 24 * 60];

    public function index(Request $request, Plant $plant): JsonResponse
    {
        Gate::authorize('view', $plant);

        $device = $plant->devices()->with('sensors')->orderBy('name')->first();
        $declared = $device?->sensors->map(fn ($sensor) => $sensor->parameter->value)->all() ?? [];

        $validated = $request->validate([
            'metric' => ['sometimes', Rule::in($declared)],
            'range' => ['sometimes', Rule::in(array_keys(self::RANGES))],
        ], [
            'metric.in' => 'This plant\'s device does not report that metric.',
        ]);

        $range = $validated['range'] ?? '24h';

        if ($device === null || $declared === []) {
            return response()->json([
                'parameter' => null,
                'range' => $range,
                'threshold' => ['min' => null, 'max' => null],
                'points' => [],
            ]);
        }

        $parameter = ThresholdParameter::from($validated['metric'] ?? $declared[0]);
        $column = $parameter->readingColumn();
        [$min, $max] = $device->thresholdsFor($parameter);

        $points = Reading::query()
            ->where('device_id', $device->id)
            ->where('measured_at', '>=', now()->subMinutes(self::RANGES[$range]))
            ->whereNotNull($column)
            ->orderBy('measured_at')
            ->get([$column, 'measured_at'])
            // [timestamp in ms, value] is the compact pair ApexCharts reads.
            ->map(fn (Reading $reading) => [
                $reading->measured_at->getTimestamp() * 1000,
                (float) $reading->getAttribute($column),
            ])
            ->all();

        return response()->json([
            'parameter' => $parameter->value,
            'label' => $parameter->label(),
            'unit' => $parameter->unit(),
            'decimals' => $parameter->decimals(),
            'range' => $range,
            'threshold' => ['min' => $min, 'max' => $max],
            'points' => $points,
        ]);
    }
}
