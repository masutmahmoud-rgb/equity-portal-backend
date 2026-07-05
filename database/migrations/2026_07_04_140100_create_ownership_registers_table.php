<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('portfolio_valuation_id')->constrained('portfolio_valuations')->restrictOnDelete();
            $table->date('effective_date');
            $table->string('status')->default('Draft');
            $table->unsignedInteger('version');
            $table->boolean('is_current')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'is_current']);
            $table->unique(['company_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_registers');
    }
};
