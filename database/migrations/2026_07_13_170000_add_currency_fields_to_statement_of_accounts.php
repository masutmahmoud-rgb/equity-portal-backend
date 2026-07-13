<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'original_amount')) {
                $table->decimal('original_amount', 15, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('statement_of_accounts', 'original_currency')) {
                $table->string('original_currency', 3)->nullable()->after('original_amount');
            }

            if (! Schema::hasColumn('statement_of_accounts', 'exchange_rate')) {
                $table->decimal('exchange_rate', 15, 6)->nullable()->after('original_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('statement_of_accounts', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }

            if (Schema::hasColumn('statement_of_accounts', 'original_currency')) {
                $table->dropColumn('original_currency');
            }

            if (Schema::hasColumn('statement_of_accounts', 'original_amount')) {
                $table->dropColumn('original_amount');
            }
        });
    }
};
