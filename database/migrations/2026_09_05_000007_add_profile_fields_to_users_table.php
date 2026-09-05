<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('company', 100)->nullable()->after('phone');
            $table->string('role', 20)->default('member')->after('company')->comment('member|admin');
            $table->timestamp('marketing_agreed_at')->nullable()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('marketing_agreed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'company', 'role', 'marketing_agreed_at', 'last_login_at']);
        });
    }
};
