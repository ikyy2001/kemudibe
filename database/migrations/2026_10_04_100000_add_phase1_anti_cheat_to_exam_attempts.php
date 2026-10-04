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
            $table->decimal('violation_score', 5, 1)->default(0.0)->after('violation_count');
            $table->decimal('max_violation_score', 5, 1)->default(3.0)->after('violation_score');
            $table->boolean('is_frozen')->default(false)->after('max_violation_score');
            $table->timestamp('frozen_at')->nullable()->after('is_frozen');
            $table->unsignedInteger('freeze_count')->default(0)->after('frozen_at');
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_activity_at');
            $table->unsignedInteger('offline_gaps_count')->default(0)->after('freeze_count');
            $table->unsignedInteger('total_offline_seconds')->default(0)->after('offline_gaps_count');
        });

        Schema::table('exam_violations', function (Blueprint $table) {
            $table->decimal('weight', 3, 1)->default(1.0)->after('violation_type');
            $table->unsignedInteger('duration_seconds')->default(0)->after('weight');
            $table->boolean('is_offline_gap')->default(false)->after('duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_violations', function (Blueprint $table) {
            $table->dropColumn(['weight', 'duration_seconds', 'is_offline_gap']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'violation_score',
                'max_violation_score',
                'is_frozen',
                'frozen_at',
                'freeze_count',
                'last_heartbeat_at',
                'offline_gaps_count',
                'total_offline_seconds',
            ]);
        });
    }
};
