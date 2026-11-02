<?php

namespace App\Support;

use App\Enums\ThresholdParameter;
use App\Models\Device;
use App\Models\Reading;

/**
 * The latest state of one parameter on one device, shaped for the dashboard.
 *
 * The classification (good / watch / alert / offline) is decided here on the
 * server, never in the browser, so every client reads the same verdict from
 * the same thresholds. Values are sent as raw numbers together with the
 * number of decimals to show; formatting them is the frontend's job.
 */
final class MetricSnapshot
{
    /**
     * @return array{parameter: string, label: string, short: string, unit: string, decimals: int, value: float|null, status: string, trend: string, threshold: array{min: float|null, max: float|null}}
     */
    public static function for(Device $device, ThresholdParameter $parameter, ?Reading $previous = null): array
    {
        $column = $parameter->readingColumn();

        $raw = $device->latestReading?->getAttribute($column);
        $value = $raw === null ? null : (float) $raw;

        $previousRaw = $previous?->getAttribute($column);
        $previousValue = $previousRaw === null ? null : (float) $previousRaw;

        [$min, $max] = $device->thresholdsFor($parameter);

        return [
            'parameter' => $parameter->value,
            'label' => $parameter->label(),
            'short' => $parameter->short(),
            'unit' => $parameter->unit(),
            'decimals' => $parameter->decimals(),
            'value' => $value,
            'status' => $device->statusOf($parameter, $value),
            'trend' => self::trend($value, $previousValue),
            'threshold' => ['min' => $min, 'max' => $max],
        ];
    }

    private static function trend(?float $value, ?float $previous): string
    {
        if ($value === null || $previous === null) {
            return 'flat';
        }

        return match ($value <=> $previous) {
            1 => 'up',
            -1 => 'down',
            default => 'flat',
        };
    }
}
