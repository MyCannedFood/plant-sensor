<?php

namespace App\Models;

use App\Enums\ThresholdParameter;
use Database\Factories\DeviceSensorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parameter a device is physically able to report.
 *
 * The row is the hardware declaration, not a measurement: a device carries a
 * DHT22, an MH-Z19C, or both, and that is what puts one, two, or three rows
 * here. A null measurement in readings can only be interpreted by looking
 * here first, so this table and the readings it governs must agree.
 */
#[Fillable(['device_id', 'parameter'])]
class DeviceSensor extends Model
{
    /** @use HasFactory<DeviceSensorFactory> */
    use HasFactory;

    /**
     * Get the device that carries this sensor.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameter' => ThresholdParameter::class,
        ];
    }
}
