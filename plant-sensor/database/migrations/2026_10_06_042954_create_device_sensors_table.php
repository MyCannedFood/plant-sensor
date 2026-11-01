<?php

use App\Enums\ThresholdParameter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Declares which parameters a device is able to report. A device carries a
     * DHT22, an MH-Z19C, or both, and that combination is what decides the
     * rows: a DHT22 alone contributes temperature and humidity, an MH-Z19C
     * alone contributes co2, and carrying both contributes all three.
     *
     * This is the table that lets a null measurement be read correctly. Without
     * it, a null co2 is ambiguous between a device with no CO2 sensor and a
     * device whose sensor failed, and the second case is worth alerting on
     * while the first is not.
     */
    public function up(): void
    {
        Schema::create('device_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->enum('parameter', ThresholdParameter::cases());
            $table->timestamps();

            $table->unique(['device_id', 'parameter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_sensors');
    }
};
