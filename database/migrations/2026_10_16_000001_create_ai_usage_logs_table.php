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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tier', 30);
            $table->string('scope', 30);
            $table->boolean('cache_hit')->default(false);
            $table->string('status', 30); // ok, error, rate_limited
            $table->string('error_code', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->integer('latency_ms')->default(0);
            $table->string('ip_hash', 64);
            $table->string('question_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            // Indexes for fast aggregation and querying
            $table->index('created_at');
            $table->index(['tier', 'created_at']);
            $table->index('user_id');
            $table->index('question_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
