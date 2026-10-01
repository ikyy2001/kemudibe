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
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('name');
            $table->unsignedInteger('passing_grade')->default(75)->after('code');
            $table->text('drive_url')->nullable()->after('content');
            $table->string('status', 20)->default('published')->after('teacher_id');
            // make topic_id nullable if it was strictly constrained
            $table->foreignId('topic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['code', 'passing_grade', 'drive_url', 'status']);
        });
    }
};
