<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\ThresholdParameter;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'serial_number', 'api_token', 'firmware_version', 'status', 'last_seen_at'])]
#[Hidden(['api_token'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Get the plant the device is monitoring.
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * Get the readings recorded by the device.
     *
     * @return HasMany<Reading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    /**
     * Get the thresholds configured for the device.
     *
     * @return HasMany<Threshold, $this>
     */
    public function thresholds(): HasMany
    {
        return $this->hasMany(Threshold::class);
    }

    /**
     * Get the alerts raised for the device.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the parameters this device is physically able to report.
     *
     * @return HasMany<DeviceSensor, $this>
     */
    public function sensors(): HasMany
    {
        return $this->hasMany(DeviceSensor::class);
    }

    /**
     * Determine whether the device carries a sensor for the parameter.
     *
     * This is the check that decides whether a null measurement means "no
     * sensor" or "sensor failed", and it is the reason readings can be
     * stored with a subset of the parameters filled in.
     */
    public function declares(ThresholdParameter $parameter): bool
    {
        if ($this->relationLoaded('sensors')) {
            return $this->sensors->contains('parameter', $parameter);
        }

        return $this->sensors()->where('parameter', $parameter)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
