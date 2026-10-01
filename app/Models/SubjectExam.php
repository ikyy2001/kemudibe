<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectExam extends Model
{
    use HasFactory;
    protected $fillable = [
        'subject_id',
        'topic_id',
        'name',
        'about',
        'duration_minutes',
        'token',
        'token_expires_at',
        'total_points',
        'passing_grade',
        'category',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'duration_minutes' => 'integer',
        'passing_grade' => 'integer',
        'token_expires_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function examQuestions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class);
    }

    public function examAttempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * Calculate and update total points from all exam questions
     */
    public function calculateTotalPoints(): int
    {
        $totalPoints = $this->examQuestions()->sum('points');
        $this->update(['total_points' => $totalPoints]);
        return $totalPoints;
    }

    /**
     * Get total points, calculating if needed
     */
    public function getTotalPointsAttribute($value): int
    {
        // If total_points is 0 or null, calculate it
        if (!$value) {
            return $this->calculateTotalPoints();
        }
        return $value;
    }
}
