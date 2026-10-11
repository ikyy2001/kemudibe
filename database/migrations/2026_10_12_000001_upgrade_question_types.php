<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Upgrade exam_questions table
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->longText('name')->change();
            $table->string('category')->nullable()->after('type');
            $table->boolean('shuffle_options')->default(true)->after('category');
        });

        // Convert existing multiple_choice records to single_choice
        DB::table('exam_questions')->where('type', 'multiple_choice')->update(['type' => 'single_choice']);

        // 2. Upgrade question_options table
        Schema::table('question_options', function (Blueprint $table) {
            $table->longText('name')->change();
            $table->integer('sort_order')->default(0)->after('is_correct');
        });

        // 3. Upgrade question_answers table
        Schema::table('question_answers', function (Blueprint $table) {
            $table->json('answer_data')->nullable()->after('answer_text');
            $table->string('result_status', 20)->default('pending')->after('has_passed'); // 'correct', 'partial', 'wrong', 'pending'
            $table->decimal('points_earned', 8, 2)->default(0.00)->change();
        });

        // 4. Upgrade exam_attempts table for partial score precision
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->decimal('points_earned', 8, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->integer('points_earned')->default(0)->change();
        });

        Schema::table('question_answers', function (Blueprint $table) {
            $table->dropColumn(['answer_data', 'result_status']);
            $table->integer('points_earned')->default(0)->change();
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn(['sort_order']);
            $table->text('name')->change();
        });

        DB::table('exam_questions')->where('type', 'single_choice')->update(['type' => 'multiple_choice']);

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropColumn(['category', 'shuffle_options']);
            $table->text('name')->change();
        });
    }
};
