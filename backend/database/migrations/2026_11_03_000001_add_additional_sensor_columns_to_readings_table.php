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
        Schema::table('readings', function (Blueprint $table) {
            $table->decimal('ph', 3, 1)->nullable()->after('humidity')->comment('pH level');
            $table->unsignedInteger('ec')->nullable()->after('ph')->comment('Electrical conductivity in µS/cm');
            $table->decimal('water_temperature', 5, 2)->nullable()->after('ec')->comment('Water temperature in degrees Celsius');
            $table->unsignedInteger('light')->nullable()->after('water_temperature')->comment('Light intensity in lux');
            $table->unsignedTinyInteger('water_level')->nullable()->after('light')->comment('Water level in percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['ph', 'ec', 'water_temperature', 'light', 'water_level']);
        });
    }
};