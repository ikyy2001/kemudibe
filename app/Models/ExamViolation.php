<?php

namespace App\Models;

use App\Models\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamViolation extends Model
{
    use HasFactory, BelongsToInstitution;

    protected $fillable = [
        'institution_id',
        'exam_attempt_id',
        'violation_type',
        'weight',
        'duration_seconds',
        'is_offline_gap',
        'details',
        'occurred_at',
    ];

    protected $casts = [
        'weight' => 'float',
        'duration_seconds' => 'integer',
        'is_offline_gap' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    public function examAttempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class);
    }
}
