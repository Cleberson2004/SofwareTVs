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
        Schema::create('display_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('rows');
            $table->unsignedSmallInteger('columns');
            $table->unsignedInteger('display_width');
            $table->unsignedInteger('display_height');
            $table->string('orientation')->default('horizontal');
            $table->unsignedInteger('total_width');
            $table->unsignedInteger('total_height');
            $table->decimal('refresh_rate', 6, 2)->default(60);
            $table->integer('overlap_x')->default(0);
            $table->integer('overlap_y')->default(0);
            $table->json('nvapi_payload')->nullable();
            $table->boolean('is_tested')->default(false);
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('display_profiles');
    }
};
