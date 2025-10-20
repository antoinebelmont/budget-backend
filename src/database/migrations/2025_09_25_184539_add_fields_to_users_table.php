<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name');
            $table->string('last_name')->after('first_name');
            $table->timestamp('email_verified_at')->nullable()->change();
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3)->default('USD');
            $table->json('preferences')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('avatar_url')->nullable();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'is_active', 'timezone',
                'currency', 'preferences', 'last_login_at', 'avatar_url'
            ]);
        });
    }
};
