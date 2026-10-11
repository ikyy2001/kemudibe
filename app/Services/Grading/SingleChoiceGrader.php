<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;
use App\Models\QuestionOption;

class SingleChoiceGrader implements GraderInterface
{
    public function grade(ExamQuestion $question, mixed $rawAnswer): GraderResult
    {
        $options = $question->questionOptions;
        $correctOption = $options->firstWhere('is_correct', true);

        // Extract selected option ID or text
        $selectedId = null;
        $answerText = null;

        if (is_array($rawAnswer)) {
            $selected = $rawAnswer['selected'] ?? $rawAnswer;
            if (is_array($selected) && !empty($selected)) {
                $selectedId = (int) reset($selected);
            }
        } elseif (is_numeric($rawAnswer)) {
            $numVal = (int) $rawAnswer;
            // Check if any option name matches this number literally (e.g. math answer "3")
            $matchedByText = $options->first(fn($opt) => trim((string)$opt->name) === trim((string)$rawAnswer));
            if ($matchedByText) {
                $selectedId = $matchedByText->id;
                $answerText = $matchedByText->name;
            } elseif ($options->contains('id', $numVal)) {
                $selectedId = $numVal;
            }
        } elseif (is_string($rawAnswer) && !empty(trim($rawAnswer))) {
            $strVal = trim($rawAnswer);
            // 1. Try matching by option text
            $matchedOption = $options->first(fn($opt) => strcasecmp(trim((string)$opt->name), $strVal) === 0);
            if ($matchedOption) {
                $selectedId = $matchedOption->id;
                $answerText = $matchedOption->name;
            } elseif (ctype_digit($strVal) && $options->contains('id', (int)$strVal)) {
                // 2. Try matching by option ID
                $selectedId = (int) $strVal;
            } else {
                $answerText = $strVal;
            }
        }

        if ($selectedId !== null) {
            $selectedOpt = $options->firstWhere('id', $selectedId);
            if ($selectedOpt) {
                $answerText = $selectedOpt->name;
            }
        }

        $isCorrect = false;
        if ($correctOption && $selectedId !== null && $correctOption->id === $selectedId) {
            $isCorrect = true;
        } elseif ($correctOption && $answerText !== null && strcasecmp(trim((string)$answerText), trim((string)$correctOption->name)) === 0) {
            $isCorrect = true;
        }

        $pointsEarned = $isCorrect ? (float) $question->points : 0.0;
        $hasPassed = $isCorrect;
        $status = $isCorrect ? 'correct' : 'wrong';

        $answerData = $selectedId !== null ? ['selected' => [$selectedId]] : null;

        return new GraderResult(
            pointsEarned: $pointsEarned,
            hasPassed: $hasPassed,
            resultStatus: $status,
            answerText: $answerText,
            answerData: $answerData
        );
    }
}
