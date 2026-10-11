<?php

namespace App\Services\Grading;

use App\Models\ExamQuestion;
use App\Models\QuestionOption;

class MultipleAnswerGrader implements GraderInterface
{
    public function grade(ExamQuestion $question, mixed $rawAnswer): GraderResult
    {
        $options = $question->questionOptions;
        $correctIds = $options->where('is_correct', true)->pluck('id')->all();
        $totalCorrect = count($correctIds);

        // Normalize raw answer into list of option IDs
        $selectedIds = $this->parseSelectedIds($rawAnswer, $options);

        if ($totalCorrect === 0) {
            return new GraderResult(
                pointsEarned: 0.0,
                hasPassed: false,
                resultStatus: 'wrong',
                answerText: implode(', ', $this->getOptionNames($options, $selectedIds)),
                answerData: ['selected' => $selectedIds]
            );
        }

        $selectedCollect = collect($selectedIds);
        $correctCollect = collect($correctIds);

        $right = $selectedCollect->intersect($correctCollect)->count();
        $wrong = $selectedCollect->diff($correctCollect)->count();
        $net = max(0, $right - $wrong);

        $pointsEarned = round(($net / $totalCorrect) * $question->points, 2);

        if ($net === $totalCorrect && $wrong === 0 && count($selectedIds) === $totalCorrect) {
            $status = 'correct';
            $hasPassed = true;
        } elseif ($pointsEarned > 0) {
            $status = 'partial';
            $hasPassed = true;
        } else {
            $status = 'wrong';
            $hasPassed = false;
        }

        $answerText = implode(', ', $this->getOptionNames($options, $selectedIds));

        return new GraderResult(
            pointsEarned: (float) $pointsEarned,
            hasPassed: $hasPassed,
            resultStatus: $status,
            answerText: $answerText,
            answerData: ['selected' => $selectedIds]
        );
    }

    private function parseSelectedIds(mixed $rawAnswer, $options): array
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
            $list = $rawAnswer['selected'] ?? $rawAnswer;
            if (!is_array($list)) {
                $list = [$list];
            }
            return array_values(array_filter(array_map('intval', $list)));
        }

        if (is_numeric($rawAnswer)) {
            return [(int) $rawAnswer];
        }

        if (is_string($rawAnswer)) {
            // Might be comma separated text or IDs
            $parts = array_map('trim', explode(',', $rawAnswer));
            $ids = [];
            foreach ($parts as $part) {
                if (ctype_digit($part)) {
                    $ids[] = (int) $part;
                } else {
                    $matched = $options->first(fn($opt) => strcasecmp(trim($opt->name), $part) === 0);
                    if ($matched) {
                        $ids[] = $matched->id;
                    }
                }
            }
            return $ids;
        }

        return [];
    }

    private function getOptionNames($options, array $selectedIds): array
    {
        return $options->whereIn('id', $selectedIds)->pluck('name')->all();
    }
}
