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
            $table->unsignedInteger('passing_grade')->nullable()->after('total_points');
            $table->string('category', 50)->default('daily_quiz')->after('passing_grade');
            $table->foreignId('topic_id')->nullable()->after('subject_id')->constrained('topics')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_exams', function (Blueprint $table) {
            $table->dropForeign(['topic_id']);
            $table->dropColumn(['passing_grade', 'category', 'topic_id']);
        });
    }
};
