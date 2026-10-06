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
        // 1. topics
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->dropUnique('topics_name_unique');
            $table->unique(['institution_id', 'name'], 'topics_inst_name_unique');
            $table->index(['institution_id', 'subject_id'], 'topics_inst_subject_idx');
        });

        // 2. subjects
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->dropUnique('subjects_name_unique');
            $table->unique(['institution_id', 'name'], 'subjects_inst_name_unique');
            $table->index(['institution_id', 'teacher_id'], 'subjects_inst_teacher_idx');
        });

        // 3. class_rooms
        Schema::table('class_rooms', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->dropUnique('class_rooms_name_unique');
            $table->unique(['institution_id', 'name'], 'class_rooms_inst_name_unique');
        });

        // 4. class_students
        Schema::table('class_students', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'class_room_id'], 'cs_inst_classroom_idx');
            $table->index(['institution_id', 'student_id'], 'cs_inst_student_idx');
        });

        // 5. class_subjects
        Schema::table('class_subjects', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'class_room_id'], 'csub_inst_classroom_idx');
            $table->index(['institution_id', 'subject_id'], 'csub_inst_subject_idx');
        });

        // 6. lessons
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'subject_id'], 'lessons_inst_subject_idx');
            $table->index(['institution_id', 'topic_id'], 'lessons_inst_topic_idx');
        });

        // 7. subject_exams
        Schema::table('subject_exams', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'subject_id'], 'se_inst_subject_idx');
        });

        // 8. exam_questions
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'subject_exam_id'], 'eq_inst_exam_idx');
        });

        // 9. question_options
        Schema::table('question_options', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'exam_question_id'], 'qo_inst_question_idx');
        });

        // 10. question_answers
        Schema::table('question_answers', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'student_id'], 'qa_inst_student_idx');
            $table->index(['institution_id', 'exam_question_id'], 'qa_inst_question_idx');
        });

        // 11. exam_attempts
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'student_id'], 'ea_inst_student_idx');
            $table->index(['institution_id', 'subject_exam_id'], 'ea_inst_exam_idx');
        });

        // 12. exam_violations
        Schema::table('exam_violations', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'exam_attempt_id'], 'ev_inst_attempt_idx');
        });

        // 13. classroom_activities
        Schema::table('classroom_activities', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'class_room_id'], 'ca_inst_classroom_idx');
        });

        // 14. projects
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('institution_id')->after('id')->constrained('institutions')->onDelete('cascade');
            $table->index(['institution_id', 'class_room_id'], 'proj_inst_classroom_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'projects',
            'classroom_activities',
            'exam_violations',
            'exam_attempts',
            'question_answers',
            'question_options',
            'exam_questions',
            'subject_exams',
            'lessons',
            'class_subjects',
            'class_students',
            'class_rooms',
            'subjects',
            'topics',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropForeign([$table . '_institution_id_foreign']);
                $t->dropColumn('institution_id');
            });
        }

        Schema::table('topics', function (Blueprint $table) {
            $table->unique('name', 'topics_name_unique');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->unique('name', 'subjects_name_unique');
        });

        Schema::table('class_rooms', function (Blueprint $table) {
            $table->unique('name', 'class_rooms_name_unique');
        });
    }
};
