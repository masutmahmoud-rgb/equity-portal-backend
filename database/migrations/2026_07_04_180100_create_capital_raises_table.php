<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_raises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('portfolio_valuation_id')->constrained('portfolio_valuations')->restrictOnDelete();
            $table->date('effective_date');
            $table->decimal('raise_amount', 15, 2);
            $table->string('participation_method');
            $table->string('status')->default('Draft');
            $table->foreignId('ownership_register_id')->nullable()->constrained('ownership_registers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->json('generated_transactions')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_raises');
    }
};
