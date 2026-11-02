<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A reading is identified by the device that sent it plus the moment the
     * device says it measured. That pair is what makes a retry safe to absorb
     * instead of storing the same observation twice: a device that never saw
     * the response to a successful post will send it again, and the second
     * attempt collides here.
     *
     * The order of these two statements is load-bearing. The foreign key on
     * device_id is backed by the plain index, so the unique index is added
     * alongside it first and the now-redundant plain one is dropped only once
     * the key has something else to stand on. Dropping it first fails with
     * "Cannot drop index: needed in a foreign key constraint".
     */
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->unique(['device_id', 'measured_at']);
        });

        Schema::table('readings', function (Blueprint $table) {
            $table->dropIndex(['device_id', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The plain index is restored before the unique one is dropped, so the
     * foreign key is never left without an index to back it.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->index(['device_id', 'measured_at']);
        });

        Schema::table('readings', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'measured_at']);
        });
    }
};
