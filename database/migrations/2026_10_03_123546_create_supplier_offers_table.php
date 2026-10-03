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
        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_line_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->decimal('unit_price', 14, 4);
            $table->decimal('max_share', 4, 3)->default(1);
            $table->unsignedTinyInteger('duration_months')->nullable();
            $table->date('quoted_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_offers');
    }
};
