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
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cost_line_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoiced_on');
            $table->string('description');
            $table->decimal('quantity', 14, 3);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->index(['company_id', 'invoiced_on']);
            $table->index(['cost_line_id', 'invoiced_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
