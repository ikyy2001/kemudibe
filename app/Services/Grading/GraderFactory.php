<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;
use InvalidArgumentException;

class GraderFactory
{
    public static function make(string $type): GraderInterface
    {
        return match ($type) {
            ExamQuestion::TYPE_SINGLE_CHOICE, 'multiple_choice' => new SingleChoiceGrader(),
            ExamQuestion::TYPE_MULTIPLE_ANSWER => new MultipleAnswerGrader(),
            ExamQuestion::TYPE_TRUE_FALSE_TABLE => new TrueFalseTableGrader(),
            ExamQuestion::TYPE_ESSAY => new EssayGrader(),
            default => throw new InvalidArgumentException("Unsupported question type: [{$type}]"),
        };
    }
}
