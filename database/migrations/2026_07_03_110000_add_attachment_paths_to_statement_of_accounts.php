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
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'attachment_paths')) {
                $table->text('attachment_paths')->nullable();
            }

            if (Schema::hasColumn('statement_of_accounts', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }
            if (Schema::hasColumn('statement_of_accounts', 'attachment_paths')) {
                $table->dropColumn('attachment_paths');
            }
        });
    }
};
