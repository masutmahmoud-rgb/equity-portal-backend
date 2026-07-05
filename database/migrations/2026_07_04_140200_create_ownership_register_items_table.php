<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_register_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_register_id')->constrained('ownership_registers')->cascadeOnDelete();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->decimal('ownership_percentage', 8, 4);
            $table->timestamps();

            $table->unique(['ownership_register_id', 'investor_id'], 'own_reg_items_reg_inv_uidx');
            $table->index('investor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_register_items');
    }
};
