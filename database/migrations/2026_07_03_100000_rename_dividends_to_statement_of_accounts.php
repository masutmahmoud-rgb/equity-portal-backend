<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('dividends') && ! Schema::hasTable('statement_of_accounts')) {
            Schema::rename('dividends', 'statement_of_accounts');
        }

        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'investor_id')) {
                $table->foreignId('investor_id')->nullable()->constrained('investors')->onDelete('cascade');
            }

            if (! Schema::hasColumn('statement_of_accounts', 'transaction_type')) {
                $table->enum('transaction_type', ['Dividend', 'Withdrawal'])->default('Dividend');
            }

            if (! Schema::hasColumn('statement_of_accounts', 'transaction_date')) {
                $table->timestamp('transaction_date')->nullable();
            }

            if (! Schema::hasColumn('statement_of_accounts', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
        });

        if (Schema::hasColumn('statement_of_accounts', 'investment_id')) {
            DB::table('statement_of_accounts')
                ->whereNull('investor_id')
                ->update(['investor_id' => DB::raw('(SELECT investor_id FROM investments WHERE investments.id = statement_of_accounts.investment_id)')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('statement_of_accounts', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
            if (Schema::hasColumn('statement_of_accounts', 'transaction_date')) {
                $table->dropColumn('transaction_date');
            }
            if (Schema::hasColumn('statement_of_accounts', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
            if (Schema::hasColumn('statement_of_accounts', 'investor_id')) {
                $table->dropForeign(['investor_id']);
                $table->dropColumn('investor_id');
            }
        });

        if (Schema::hasTable('statement_of_accounts')) {
            Schema::rename('statement_of_accounts', 'dividends');
        }
    }
};
