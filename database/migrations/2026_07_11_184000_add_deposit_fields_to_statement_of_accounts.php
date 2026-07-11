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
        if (! Schema::hasTable('statement_of_accounts')) {
            return;
        }

        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'entry_direction')) {
                $table->string('entry_direction', 10)->nullable()->after('transaction_type');
            }

            if (! Schema::hasColumn('statement_of_accounts', 'description')) {
                $table->text('description')->nullable()->after('transaction_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('statement_of_accounts')) {
            return;
        }

        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('statement_of_accounts', 'entry_direction')) {
                $table->dropColumn('entry_direction');
            }

            if (Schema::hasColumn('statement_of_accounts', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
