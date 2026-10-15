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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_display_profile_id')->nullable()->constrained('display_profiles')->nullOnDelete();
            $table->string('name');
            $table->uuid('device_uuid')->unique();
            $table->string('credential_hash')->nullable();
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('agent_version')->nullable();
            $table->string('windows_version')->nullable();
            $table->string('gpu_name')->nullable();
            $table->string('driver_version')->nullable();
            $table->string('display_provider')->default('Mock');
            $table->boolean('simulation_mode')->default(true);
            $table->boolean('is_revoked')->default(false);
            $table->timestamp('registered_at')->nullable();
            $table->json('display_state')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
