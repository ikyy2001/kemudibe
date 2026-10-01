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
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('current_exam_device_token', 64)->nullable()->after('completed_at');
            $table->timestamp('last_activity_at')->nullable()->after('current_exam_device_token');
            $table->unsignedInteger('violation_count')->default(0)->after('last_activity_at');
            $table->unsignedInteger('max_violations')->default(3)->after('violation_count');
            $table->string('forced_reason')->nullable()->after('max_violations');
        });

        Schema::create('exam_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('exam_attempts')->onDelete('cascade');
            $table->string('violation_type', 64);
            $table->text('details')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['exam_attempt_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_violations');

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'current_exam_device_token',
                'last_activity_at',
                'violation_count',
                'max_violations',
                'forced_reason'
            ]);
        });
    }
};
