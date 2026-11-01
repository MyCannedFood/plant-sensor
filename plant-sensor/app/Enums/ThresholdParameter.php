<?php

namespace App\Enums;

enum ThresholdParameter: string
{
    case Co2 = 'co2';
    case Temperature = 'temperature';
    case Humidity = 'humidity';

    /**
     * Get the reading column that this parameter is measured from.
     */
    public function readingColumn(): string
    {
        return match ($this) {
            self::Co2 => 'co2',
            self::Temperature => 'temperature',
            self::Humidity => 'humidity',
        };
    }

    /**
     * Human-readable label for dashboards.
     */
    public function label(): string
    {
        return match ($this) {
            self::Co2 => 'CO₂',
            self::Temperature => 'Temperature',
            self::Humidity => 'Humidity',
        };
    }

    /**
     * Compact label for narrow controls like segmented buttons.
     */
    public function short(): string
    {
        return match ($this) {
            self::Co2 => 'CO₂',
            self::Temperature => 'Temp',
            self::Humidity => 'Humidity',
        };
    }

    /**
     * Unit shown next to measured values.
     */
    public function unit(): string
    {
        return match ($this) {
            self::Co2 => 'ppm',
            self::Temperature => '°C',
            self::Humidity => '%',
        };
    }

    /**
     * Decimal places shown on the dashboard. CO₂ and humidity are read as
     * whole numbers in practice; temperature is meaningful to a tenth.
     */
    public function decimals(): int
    {
        return $this === self::Temperature ? 1 : 0;
    }

    /**
     * The sensor module that reports this parameter on our nodes.
     */
    public function sensorModel(): string
    {
        return match ($this) {
            self::Co2 => 'MH-Z19C',
            self::Temperature, self::Humidity => 'DHT22',
        };
    }
}
