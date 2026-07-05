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
        Schema::create('portfolio_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
            $table->string('valuation_period', 20);
            $table->decimal('indicative_value', 15, 2);
            $table->decimal('profit', 15, 2);
            $table->date('valuation_date');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('Draft');
            $table->timestamps();

            $table->index(['investor_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index('valuation_date');
            $table->index('valuation_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_valuations');
    }
};
