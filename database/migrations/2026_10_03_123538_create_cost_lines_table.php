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
        Schema::create('cost_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_driver_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('unit')->nullable();
            $table->boolean('scales_with_volume')->default(false);
            $table->boolean('storable')->default(false);
            $table->decimal('storage_cost_rate', 6, 4)->default(0);
            $table->string('mapping_status')->default('suggested');
            $table->decimal('mapping_confidence', 4, 3)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_lines');
    }
};
