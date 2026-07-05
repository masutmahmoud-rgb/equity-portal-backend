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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10);
            $table->decimal('exchange_rate', 15, 6);
            $table->date('effective_date');
            $table->string('status', 20)->default('Active');
            $table->timestamps();

            $table->index(['currency_code', 'status', 'effective_date']);
            $table->unique(['currency_code', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
