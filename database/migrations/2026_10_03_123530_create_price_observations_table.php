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
        Schema::create('price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_driver_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->decimal('value', 14, 4);
            $table->timestamps();

            $table->unique(['price_driver_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
