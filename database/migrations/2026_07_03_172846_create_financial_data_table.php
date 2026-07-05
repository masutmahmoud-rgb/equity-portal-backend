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
        Schema::create('financial_data', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // profit | indicative_value
            $table->unsignedSmallInteger('year');
            $table->enum('half_year', ['H1', 'H2']);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'year', 'half_year']);
            $table->unique(['type', 'year', 'half_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_data');
    }
};
