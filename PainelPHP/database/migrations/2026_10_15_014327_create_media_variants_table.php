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
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('display_profile_id')->constrained()->cascadeOnDelete();
            $table->string('variant_type')->default('normalized');
            $table->string('path');
            $table->string('preview_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('adjustment_mode');
            $table->json('processing_details');
            $table->string('sha256', 64);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['display_profile_id', 'approved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
