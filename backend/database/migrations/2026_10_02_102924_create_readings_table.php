<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->dateTime('measured_at');
            $table->unsignedSmallInteger('co2')->comment('CO2 concentration in ppm');
            $table->decimal('temperature', 5, 2)->comment('Air temperature in degrees Celsius');
            $table->unsignedTinyInteger('humidity')->comment('Relative humidity in percent');
            $table->index(['device_id', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
