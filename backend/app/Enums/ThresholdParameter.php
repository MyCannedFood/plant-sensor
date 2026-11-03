<?php

namespace App\Enums;

enum ThresholdParameter: string
{
    case Co2 = 'co2';
    case Temperature = 'temperature';
    case Humidity = 'humidity';
    case Ph = 'ph';
    case Ec = 'ec';
    case WaterTemperature = 'water_temperature';
    case Light = 'light';
    case WaterLevel = 'water_level';

    /**
     * Get the reading column that this parameter is measured from.
     */
    public function readingColumn(): string
    {
        return match ($this) {
            self::Co2 => 'co2',
            self::Temperature => 'temperature',
            self::Humidity => 'humidity',
            self::Ph => 'ph',
            self::Ec => 'ec',
            self::WaterTemperature => 'water_temperature',
            self::Light => 'light',
            self::WaterLevel => 'water_level',
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
            self::Ph => 'pH',
            self::Ec => 'EC',
            self::WaterTemperature => 'Water Temp',
            self::Light => 'Light',
            self::WaterLevel => 'Water Level',
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
            self::Ph => 'pH',
            self::Ec => 'EC',
            self::WaterTemperature => 'Water T',
            self::Light => 'Light',
            self::WaterLevel => 'Level',
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
            self::Ph => '',
            self::Ec => 'µS/cm',
            self::WaterTemperature => '°C',
            self::Light => 'lux',
            self::WaterLevel => '%',
        };
    }

    /**
     * Decimal places shown on the dashboard.
     */
    public function decimals(): int
    {
        return match ($this) {
            self::Temperature, self::WaterTemperature, self::Ph => 1,
            self::Ec => 0,
            self::Light => 0,
            self::WaterLevel => 0,
            default => 0,
        };
    }
}