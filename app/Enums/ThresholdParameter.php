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
}
