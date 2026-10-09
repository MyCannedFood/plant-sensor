<?php

namespace App\Models;

use Database\Factories\ReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['device_id', 'measured_at', 'co2', 'temperature', 'humidity'])]
#[WithoutTimestamps]
class Reading extends Model
{
    /** @use HasFactory<ReadingFactory> */
    use HasFactory;

    /**
     * Perform any actions required after the model is booted.
     *
     * received_at is deliberately absent from the fillable list: it records
     * when this server accepted the reading, so it must never be settable from
     * a submitted payload.
     *
     * device_id is fillable for application code (the controller flows the
     * authenticated device into it), but never from a request: the validation
     * rules in StoreReadingRequest do not name it, so no submitted payload can
     * steer a reading onto another device.
     *
     * This is a safety net for application code, not a guarantee. Anything
     * running under WithoutModelEvents has the event dispatcher muted and
     * will skip it, which is why the seeder sets received_at explicitly and
     * the factory supplies its own default.
     */
    protected static function booted(): void
    {
        static::creating(function (self $reading): void {
            $reading->received_at ??= now();
        });
    }

    /**
     * Get the device that recorded the reading.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the alerts triggered by the reading.
     *
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'received_at' => 'datetime',
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
        ];
    }
}
