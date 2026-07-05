<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capital_raise_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_raise_id')->constrained('capital_raises')->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->decimal('contribution_amount', 15, 2);
            $table->decimal('current_ownership_percentage', 8, 4);
            $table->decimal('new_ownership_percentage', 8, 4)->default(0);
            $table->timestamps();

            $table->unique(['capital_raise_id', 'investor_id']);
            $table->index('investor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_raise_contributions');
    }
};
