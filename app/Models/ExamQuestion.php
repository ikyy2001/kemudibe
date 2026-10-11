<?php

namespace App\Models;

use App\Models\Traits\BelongsToInstitution;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamQuestion extends Model
{
    use HasFactory, BelongsToInstitution;

    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTIPLE_ANSWER = 'multiple_answer';
    public const TYPE_TRUE_FALSE_TABLE = 'true_false_table';
    public const TYPE_ESSAY = 'essay';

    protected $fillable = [
        'institution_id',
        'subject_exam_id',
        'name',
        'timer',
        'type',
        'category',
        'shuffle_options',
        'points',
    ];

    protected $casts = [
        'shuffle_options' => 'boolean',
        'timer' => 'integer',
        'points' => 'integer',
    ];

    protected static function booted()
    {
        static::created(function ($question) {
            $question->updateExamTotalPoints();
        });

        static::updated(function ($question) {
            $question->updateExamTotalPoints();
        });

        static::deleted(function ($question) {
            $question->updateExamTotalPoints();
        });
    }

    public function subjectExam(): BelongsTo
    {
        return $this->belongsTo(SubjectExam::class);
    }

    public function questionOptions(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function questionAnswers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class);
    }

    /**
     * Update the total points for the associated exam
     */
    public function updateExamTotalPoints(): void
    {
        if ($this->subjectExam) {
            $this->subjectExam->calculateTotalPoints();
        }
    }
}
