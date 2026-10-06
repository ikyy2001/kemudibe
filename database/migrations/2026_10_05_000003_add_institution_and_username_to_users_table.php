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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->onDelete('cascade');
            $table->string('username', 60)->nullable()->after('name');
            $table->string('email')->nullable()->change();
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');

            // Drop existing global unique on email
            $table->dropUnique('users_email_unique');

            // Add composite unique indexes
            $table->unique(['institution_id', 'username'], 'users_inst_username_unique');
            $table->unique(['institution_id', 'email'], 'users_inst_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_inst_username_unique');
            $table->dropUnique('users_inst_email_unique');
            $table->unique('email', 'users_email_unique');
            $table->dropForeign(['institution_id']);
            $table->dropColumn(['institution_id', 'username', 'must_change_password', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
