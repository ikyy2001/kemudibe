<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for query performance optimization.
     */
    public function up(): void
    {
        // 1. Index on class_rooms for grade filtering
        if (Schema::hasTable('class_rooms') && Schema::hasColumn('class_rooms', 'grade')) {
            Schema::table('class_rooms', function (Blueprint $table) {
                $table->index('grade', 'idx_classrooms_grade');
            });
        }

        // 2. Index on exam_attempts for status and completion tracking
        if (Schema::hasTable('exam_attempts')) {
            Schema::table('exam_attempts', function (Blueprint $table) {
                if (Schema::hasColumn('exam_attempts', 'is_completed')) {
                    $table->index('is_completed', 'idx_exam_attempts_is_completed');
                }
                if (Schema::hasColumn('exam_attempts', 'completed_at')) {
                    $table->index('completed_at', 'idx_exam_attempts_completed_at');
                }
            });
        }

        // 3. Index on subject_exams for active dates filtering
        if (Schema::hasTable('subject_exams')) {
            Schema::table('subject_exams', function (Blueprint $table) {
                if (Schema::hasColumn('subject_exams', 'started_at') && Schema::hasColumn('subject_exams', 'ended_at')) {
                    $table->index(['started_at', 'ended_at'], 'idx_subject_exams_dates');
                }
            });
        }

        // 4. Index on question_answers
        if (Schema::hasTable('question_answers')) {
            Schema::table('question_answers', function (Blueprint $table) {
                if (Schema::hasColumn('question_answers', 'has_passed')) {
                    $table->index('has_passed', 'idx_qa_has_passed');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('class_rooms')) {
            Schema::table('class_rooms', function (Blueprint $table) {
                $table->dropIndex('idx_classrooms_grade');
            });
        }

        if (Schema::hasTable('exam_attempts')) {
            Schema::table('exam_attempts', function (Blueprint $table) {
                $table->dropIndex('idx_exam_attempts_is_completed');
                $table->dropIndex('idx_exam_attempts_completed_at');
            });
        }

        if (Schema::hasTable('subject_exams')) {
            Schema::table('subject_exams', function (Blueprint $table) {
                $table->dropIndex('idx_subject_exams_dates');
            });
        }

        if (Schema::hasTable('question_answers')) {
            Schema::table('question_answers', function (Blueprint $table) {
                $table->dropIndex('idx_qa_has_passed');
            });
        }
    }
};
