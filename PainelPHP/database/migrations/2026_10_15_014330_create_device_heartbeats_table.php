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
        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('received_at')->index();
            $table->json('vlc_status');
            $table->json('mosaic_status')->nullable();
            $table->unsignedInteger('desktop_width')->nullable();
            $table->unsignedInteger('desktop_height')->nullable();
            $table->unsignedSmallInteger('screens_detected')->default(0);
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('memory_usage', 5, 2)->nullable();
            $table->unsignedBigInteger('free_disk_space')->nullable();
            $table->uuid('last_command_id')->nullable();
            $table->string('display_provider')->default('Mock');
            $table->boolean('simulation_mode')->default(true);
            $table->json('diagnostics')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
    }
};
