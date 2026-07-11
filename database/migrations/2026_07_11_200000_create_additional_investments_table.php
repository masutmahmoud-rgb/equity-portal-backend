<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->unsignedSmallInteger('valuation_year');
            $table->string('valuation_half', 2);
            $table->string('card_label')->nullable();
            $table->decimal('investment_amount', 15, 2);
            $table->decimal('profit', 15, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['investor_id', 'valuation_year', 'valuation_half'], 'add_inv_partner_year_half_uidx');
            $table->index(['investor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_investments');
    }
};
