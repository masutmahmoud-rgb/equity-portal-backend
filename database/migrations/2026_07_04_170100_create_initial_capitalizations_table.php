<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('initial_capitalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('status')->default('Draft');
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->unique('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initial_capitalizations');
    }
};
