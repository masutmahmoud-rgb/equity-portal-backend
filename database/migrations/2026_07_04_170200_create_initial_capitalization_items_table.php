<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('initial_capitalization_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initial_capitalization_id')->constrained('initial_capitalizations')->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->decimal('initial_investment', 15, 2);
            $table->decimal('ownership_percentage', 8, 4);
            $table->timestamps();

            $table->unique(['initial_capitalization_id', 'investor_id'], 'init_cap_items_cap_inv_uidx');
            $table->index('investor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initial_capitalization_items');
    }
};
