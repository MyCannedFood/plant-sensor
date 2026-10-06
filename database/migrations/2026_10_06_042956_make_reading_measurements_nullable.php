<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A device does not necessarily carry a sensor for every parameter, so a
     * missing measurement has to be storable. Humidity also moves from
     * unsignedTinyInteger to decimal(5,2): the DHT22 resolves 0.1 %RH and the
     * common Arduino libraries report it as a float, so an integer column
     * would truncate a reading the hardware is entitled to send.
     *
     * Co2 stays a whole number because the MH-Z19C reports integer ppm and
     * tops out far below the 65535 ceiling of the column.
     */
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->unsignedSmallInteger('co2')
                ->nullable()
                ->comment('CO2 concentration in ppm')
                ->change();

            $table->decimal('temperature', 5, 2)
                ->nullable()
                ->comment('Air temperature in degrees Celsius')
                ->change();

            $table->decimal('humidity', 5, 2)
                ->nullable()
                ->comment('Relative humidity in percent')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->unsignedSmallInteger('co2')
                ->comment('CO2 concentration in ppm')
                ->change();

            $table->decimal('temperature', 5, 2)
                ->comment('Air temperature in degrees Celsius')
                ->change();

            $table->unsignedTinyInteger('humidity')
                ->comment('Relative humidity in percent')
                ->change();
        });
    }
};
