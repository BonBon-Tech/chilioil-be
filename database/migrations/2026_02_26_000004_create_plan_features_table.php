<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->enum('plan', ['basic', 'pro', 'custom']);
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
