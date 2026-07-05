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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('category', 100);
            $table->string('audience_type', 20);
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('investor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attachment')->nullable();
            $table->date('publish_date');
            $table->date('expiry_date')->nullable();
            $table->string('status', 20)->default('Draft');
            $table->timestamps();

            $table->index(['status', 'publish_date']);
            $table->index('audience_type');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
