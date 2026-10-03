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
        Schema::create('driver_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_driver_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('horizon_months');
            $table->decimal('change_low', 8, 4);
            $table->decimal('change_mid', 8, 4);
            $table->decimal('change_high', 8, 4);
            $table->string('source');
            $table->date('published_on');
            $table->timestamps();

            $table->unique(['price_driver_id', 'horizon_months']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_projections');
    }
};
