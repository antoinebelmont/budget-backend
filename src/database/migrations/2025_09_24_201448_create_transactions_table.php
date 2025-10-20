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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('memo')->nullable();
            $table->enum('cleared', ['cleared', 'uncleared', 'reconciled'])->default('uncleared');
            $table->boolean('approved')->default(true);
            $table->boolean('is_expense')->default(true);
            $table->boolean('is_income')->default(false);
            $table->string('import_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
