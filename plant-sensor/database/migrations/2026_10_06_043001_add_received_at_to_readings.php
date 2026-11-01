<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * received_at records when the server took the reading, which is not the
     * same question as when the device says it measured. The ESP8266 syncs
     * NTP and supplies measured_at, so the two usually agree closely, and the
     * gap between them is what exposes a clock that has stopped advancing.
     */
    public function up(): void
    {
        // dateTime, not timestamp: received_at is the server's wall clock at
        // acceptance, the same kind of value as measured_at, and a timestamp
        // column would impose a 2038 ceiling and a timezone conversion that
        // measured_at does not carry.
        Schema::table('readings', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable()->after('measured_at');
        });

        // Existing rows adopt the time they claimed to measure, which is the
        // closest honest answer available for a reading that arrived before
        // this column existed. A current timestamp would stamp every
        // historical row with the moment of the migration.
        DB::table('readings')
            ->whereNull('received_at')
            ->update(['received_at' => DB::raw('measured_at')]);

        Schema::table('readings', function (Blueprint $table) {
            $table->dateTime('received_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
};
