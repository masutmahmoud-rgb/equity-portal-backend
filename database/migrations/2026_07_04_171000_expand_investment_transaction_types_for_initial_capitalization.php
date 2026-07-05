<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE investment_transactions MODIFY transaction_type ENUM('Initial Investment', 'Additional Investment', 'Initial Capitalization') NOT NULL");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::create('investment_transactions_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
                $table->enum('transaction_type', ['Initial Investment', 'Additional Investment', 'Initial Capitalization']);
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('investment_id');
                $table->index('transaction_date');
            });

            DB::statement('INSERT INTO investment_transactions_tmp (id, investment_id, transaction_type, amount, transaction_date, notes, created_at, updated_at) SELECT id, investment_id, transaction_type, amount, transaction_date, notes, created_at, updated_at FROM investment_transactions');

            Schema::drop('investment_transactions');
            Schema::rename('investment_transactions_tmp', 'investment_transactions');

            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE investment_transactions MODIFY transaction_type ENUM('Initial Investment', 'Additional Investment') NOT NULL");
            return;
        }

        if ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            Schema::create('investment_transactions_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_id')->constrained('investments')->cascadeOnDelete();
                $table->enum('transaction_type', ['Initial Investment', 'Additional Investment']);
                $table->decimal('amount', 15, 2);
                $table->date('transaction_date');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('investment_id');
                $table->index('transaction_date');
            });

            DB::statement("INSERT INTO investment_transactions_tmp (id, investment_id, transaction_type, amount, transaction_date, notes, created_at, updated_at) SELECT id, investment_id, CASE WHEN transaction_type = 'Initial Capitalization' THEN 'Initial Investment' ELSE transaction_type END, amount, transaction_date, notes, created_at, updated_at FROM investment_transactions");

            Schema::drop('investment_transactions');
            Schema::rename('investment_transactions_tmp', 'investment_transactions');

            Schema::enableForeignKeyConstraints();
        }
    }
};
