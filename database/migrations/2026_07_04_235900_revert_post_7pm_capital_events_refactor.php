<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('investment_positions');
        Schema::dropIfExists('ownership_transfers');
        Schema::dropIfExists('partner_exits');

        if (! Schema::hasTable('ownership_registers')) {
            Schema::create('ownership_registers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('portfolio_valuation_id')->constrained('portfolio_valuations')->restrictOnDelete();
                $table->date('effective_date');
                $table->string('status')->default('Draft');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('is_current')->default(false);
                $table->dateTime('published_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status']);
                $table->index(['company_id', 'is_current']);
            });
        }

        if (! Schema::hasTable('ownership_register_items')) {
            Schema::create('ownership_register_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ownership_register_id')->constrained('ownership_registers')->cascadeOnDelete();
                $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
                $table->decimal('ownership_percentage', 10, 4);
                $table->timestamps();

                $table->unique(['ownership_register_id', 'investor_id'], 'own_reg_items_reg_inv_uidx');
            });
        }

        if (Schema::hasTable('capital_raises')) {
            if (Schema::hasColumn('capital_raises', 'position_version')) {
                Schema::table('capital_raises', function (Blueprint $table) {
                    $table->dropColumn('position_version');
                });
            }

            if (! Schema::hasColumn('capital_raises', 'ownership_register_id')) {
                Schema::table('capital_raises', function (Blueprint $table) {
                    $table->foreignId('ownership_register_id')->nullable()->after('status')->constrained('ownership_registers')->nullOnDelete();
                });
            }
        }

        // Remove refactor data created after 7 PM today from remaining event tables.
        $cutoff = '2026-07-04 19:00:00';
        foreach (['capital_raise_contributions', 'capital_raises', 'initial_capitalization_items', 'initial_capitalizations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_at')) {
                DB::table($table)->where('created_at', '>=', $cutoff)->delete();
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty for one-way rollback operation.
    }
};
