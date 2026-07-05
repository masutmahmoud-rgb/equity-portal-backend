<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statement_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('investment_id');
            $table->unsignedBigInteger('investor_id');
            $table->string('transaction_type');
            $table->decimal('amount', 16, 2);
            $table->string('status')->default('Pending');
            $table->dateTime('transaction_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('attachment_paths')->nullable();
            $table->timestamps();

            // optional indexes
            $table->index('company_id');
            $table->index('investment_id');
            $table->index('investor_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('statement_of_accounts');
    }
};
