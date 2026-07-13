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
        if (! Schema::hasTable('dividends')) {
            return;
        }

        Schema::table('dividends', function (Blueprint $table) {
            if (! Schema::hasColumn('dividends', 'original_currency')) {
                $table->string('original_currency', 3)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('dividends', 'exchange_rate')) {
                $table->decimal('exchange_rate', 15, 6)->nullable()->after('original_currency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('dividends')) {
            return;
        }

        Schema::table('dividends', function (Blueprint $table) {
            if (Schema::hasColumn('dividends', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }

            if (Schema::hasColumn('dividends', 'original_currency')) {
                $table->dropColumn('original_currency');
            }
        });
    }
};
