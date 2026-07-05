<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('investment_transactions', 'source')) {
                $table->string('source')->nullable()->after('transaction_type');
            }

            if (! Schema::hasColumn('investment_transactions', 'status')) {
                $table->string('status')->nullable()->after('source');
            }

            if (! Schema::hasColumn('investment_transactions', 'is_read_only')) {
                $table->boolean('is_read_only')->default(false)->after('notes');
            }

            if (! Schema::hasColumn('investment_transactions', 'capital_raise_id')) {
                $table->foreignId('capital_raise_id')->nullable()->after('is_read_only')->constrained('capital_raises')->nullOnDelete();
            }
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE investment_transactions MODIFY transaction_type ENUM('Initial Investment', 'Additional Investment', 'Initial Capitalization', 'Capital Raise') NOT NULL");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            if (Schema::hasTable('investment_transactions_tmp')) {
                Schema::drop('investment_transactions_tmp');
            }

            Schema::create('investment_transactions_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
                $table->enum('transaction_type', ['Initial Investment', 'Additional Investment', 'Initial Capitalization', 'Capital Raise']);
                $table->string('source')->nullable();
                $table->string('status')->nullable();
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('notes')->nullable();
                $table->boolean('is_read_only')->default(false);
                $table->foreignId('capital_raise_id')->nullable()->constrained('capital_raises')->nullOnDelete();
                $table->timestamps();
            });

            DB::statement('INSERT INTO investment_transactions_tmp (id, investment_id, transaction_type, source, status, amount, transaction_date, notes, is_read_only, capital_raise_id, created_at, updated_at) SELECT id, investment_id, transaction_type, source, status, amount, transaction_date, notes, is_read_only, capital_raise_id, created_at, updated_at FROM investment_transactions');

            Schema::drop('investment_transactions');
            Schema::rename('investment_transactions_tmp', 'investment_transactions');

            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE investment_transactions MODIFY transaction_type ENUM('Initial Investment', 'Additional Investment', 'Initial Capitalization') NOT NULL");
        }

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            if (Schema::hasTable('investment_transactions_tmp')) {
                Schema::drop('investment_transactions_tmp');
            }

            Schema::create('investment_transactions_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
                $table->enum('transaction_type', ['Initial Investment', 'Additional Investment', 'Initial Capitalization']);
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('notes')->nullable();
                $table->timestamps();
            });

            DB::statement("INSERT INTO investment_transactions_tmp (id, investment_id, transaction_type, amount, transaction_date, notes, created_at, updated_at) SELECT id, investment_id, CASE WHEN transaction_type = 'Capital Raise' THEN 'Additional Investment' ELSE transaction_type END, amount, transaction_date, notes, created_at, updated_at FROM investment_transactions");

            Schema::drop('investment_transactions');
            Schema::rename('investment_transactions_tmp', 'investment_transactions');

            Schema::enableForeignKeyConstraints();
        }

        Schema::table('investment_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('investment_transactions', 'capital_raise_id')) {
                $table->dropConstrainedForeignId('capital_raise_id');
            }

            if (Schema::hasColumn('investment_transactions', 'is_read_only')) {
                $table->dropColumn('is_read_only');
            }

            if (Schema::hasColumn('investment_transactions', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('investment_transactions', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
