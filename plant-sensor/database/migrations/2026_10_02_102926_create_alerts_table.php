<?php

use App\Enums\AlertDirection;
use App\Enums\AlertSeverity;
use App\Enums\ThresholdParameter;
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
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('threshold_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('parameter', ThresholdParameter::cases());
            $table->enum('direction', AlertDirection::cases());
            $table->enum('severity', AlertSeverity::cases())->nullable();
            $table->decimal('value', 8, 2)->comment('The measured value that triggered the alert');
            $table->dateTime('triggered_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'resolved_at']);
            $table->index(['parameter', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
