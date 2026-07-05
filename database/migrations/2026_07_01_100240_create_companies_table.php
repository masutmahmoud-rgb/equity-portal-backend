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
            $table->string('industry')->nullable();

            $table->decimal('total_equity', 15, 2)->default(0);
            $table->decimal('latest_valuation', 15, 2)->default(0);

            $table->enum('status', [
                'Operating',
                'Exited',
                'Pending'
            ])->default('Operating');

            $table->text('notes')->nullable();

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