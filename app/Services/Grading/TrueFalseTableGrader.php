<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;
use App\Models\QuestionOption;

class TrueFalseTableGrader implements GraderInterface
{
    public function grade(ExamQuestion $question, mixed $rawAnswer): GraderResult
    {
        $options = $question->questionOptions;
        $totalRows = $options->count();

        $studentMap = $this->parseAnswerMap($rawAnswer);

        if ($totalRows === 0) {
            return new GraderResult(
                pointsEarned: 0.0,
                hasPassed: false,
                resultStatus: 'wrong',
                answerText: '',
                answerData: ['answers' => $studentMap]
            );
        }

        $correctCount = 0;
        $textSummaries = [];

        foreach ($options as $index => $opt) {
            $optId = $opt->id;
            $hasAnswer = array_key_exists($optId, $studentMap) || array_key_exists((string)$optId, $studentMap);
            $studentValRaw = $hasAnswer ? ($studentMap[$optId] ?? $studentMap[(string)$optId]) : null;

            if ($studentValRaw !== null) {
                $studentBool = filter_var($studentValRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($studentBool !== null) {
                    if ((bool)$studentBool === (bool)$opt->is_correct) {
                        $correctCount++;
                    }
                    $label = $studentBool ? 'Benar' : 'Salah';
                    $textSummaries[] = "Pernyataan " . ($index + 1) . ": {$label}";
                }
            }
        }

        $pointsEarned = round(($correctCount / $totalRows) * $question->points, 2);

        if ($correctCount === $totalRows) {
            $status = 'correct';
            $hasPassed = true;
        } elseif ($pointsEarned > 0) {
            $status = 'partial';
            $hasPassed = true;
        } else {
            $status = 'wrong';
            $hasPassed = false;
        }

        return new GraderResult(
            pointsEarned: (float) $pointsEarned,
            hasPassed: $hasPassed,
            resultStatus: $status,
            answerText: implode('; ', $textSummaries),
            answerData: ['answers' => $studentMap]
        );
    }

    private function parseAnswerMap(mixed $rawAnswer): array
    {
        if (empty($rawAnswer)) {
            return [];
        }

        if (is_string($rawAnswer)) {
            $decoded = json_decode($rawAnswer, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $rawAnswer = $decoded;
            }
        }

        if (is_array($rawAnswer)) {
            return $rawAnswer['answers'] ?? $rawAnswer;
        }

        return [];
    }
}
