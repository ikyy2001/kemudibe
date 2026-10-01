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
        Schema::table('subject_exams', function (Blueprint $table) {
            $table->unsignedInteger('duration_minutes')->nullable()->default(60)->after('about');
            $table->string('token', 50)->nullable()->after('duration_minutes');
            $table->dateTime('token_expires_at')->nullable()->after('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_exams', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'token', 'token_expires_at']);
        });
    }
};
