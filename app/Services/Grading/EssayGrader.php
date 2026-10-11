<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;

class EssayGrader implements GraderInterface
{
    public function grade(ExamQuestion $question, mixed $rawAnswer): GraderResult
    {
        $answerText = is_string($rawAnswer) ? trim($rawAnswer) : (is_array($rawAnswer) ? json_encode($rawAnswer) : (string)$rawAnswer);
        $hasPassed = !empty(trim($answerText));

        return new GraderResult(
            pointsEarned: 0.0, // Manual grading required
            hasPassed: $hasPassed,
            resultStatus: 'pending',
            answerText: $answerText,
            answerData: null
        );
    }
}
