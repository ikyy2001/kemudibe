<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $table = 'ai_usage_logs';

    protected $fillable = [
        'user_id',
        'tier',
        'scope',
        'cache_hit',
        'status',
        'error_code',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
        'ip_hash',
        'question_hash',
        'created_at',
    ];

    protected $casts = [
        'cache_hit' => 'boolean',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'latency_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get prunable records older than 90 days.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(90));
    }

    /**
     * Associated authenticated user (if any).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
