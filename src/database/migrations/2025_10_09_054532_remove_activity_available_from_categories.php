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
        Schema::table('categories', function (Blueprint $table) {
            // Remove these if they exist as columns
            if (Schema::hasColumn('categories', 'activity')) {
                $table->dropColumn('activity');
            }
            if (Schema::hasColumn('categories', 'available')) {
                $table->dropColumn('available');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('activity', 15, 2)->default(0);
            $table->decimal('available', 15, 2)->default(0);
        });
    }
};
