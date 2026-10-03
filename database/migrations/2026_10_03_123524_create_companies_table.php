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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry');
            $table->unsignedSmallInteger('locations')->default(1);
            $table->char('currency', 3)->default('EUR');
            $table->decimal('cash_balance', 14, 2)->default(0);
            $table->decimal('minimum_cash_reserve', 14, 2)->default(0);
            $table->decimal('monthly_non_operating_outflows', 14, 2)->default(0);
            $table->decimal('price_elasticity', 6, 3)->default(-0.5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
