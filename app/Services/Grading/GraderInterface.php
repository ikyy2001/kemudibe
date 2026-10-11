<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;

interface GraderInterface
{
    /**
     * Grade a student's answer for the given exam question.
     *
     * @param ExamQuestion $question
     * @param mixed $rawAnswer
     * @return GraderResult
     */
    public function grade(ExamQuestion $question, mixed $rawAnswer): GraderResult;
}
