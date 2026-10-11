<?php

namespace Tests\Unit;

use App\Models\ExamQuestion;
use App\Models\QuestionOption;
use App\Services\Grading\EssayGrader;
use App\Services\Grading\GraderFactory;
use App\Services\Grading\MultipleAnswerGrader;
use App\Services\Grading\SingleChoiceGrader;
use App\Services\Grading\TrueFalseTableGrader;
use PHPUnit\Framework\TestCase;

class GradingTest extends TestCase
{
    private function makeQuestion(string $type, int $points, array $optionsData): ExamQuestion
    {
        $question = new ExamQuestion([
            'type' => $type,
            'points' => $points,
            'name' => 'Test Question',
        ]);

        $options = collect();
        foreach ($optionsData as $opt) {
            $option = new QuestionOption([
                'name' => $opt['name'],
                'is_correct' => $opt['is_correct'],
                'sort_order' => $opt['sort_order'] ?? 0,
            ]);
            $option->id = $opt['id'];
            $options->push($option);
        }

        $question->setRelation('questionOptions', $options);
        return $question;
    }

    public function test_single_choice_grading_correct_by_id(): void
    {
        $question = $this->makeQuestion('single_choice', 10, [
            ['id' => 101, 'name' => 'Opsi A', 'is_correct' => false],
            ['id' => 102, 'name' => 'Opsi B', 'is_correct' => true],
            ['id' => 103, 'name' => 'Opsi C', 'is_correct' => false],
        ]);

        $grader = GraderFactory::make('single_choice');
        $result = $grader->grade($question, 102);

        $this->assertEquals(10.0, $result->pointsEarned);
        $this->assertTrue($result->hasPassed);
        $this->assertEquals('correct', $result->resultStatus);
        $this->assertEquals(['selected' => [102]], $result->answerData);
    }

    public function test_single_choice_grading_correct_by_text(): void
    {
        $question = $this->makeQuestion('single_choice', 10, [
            ['id' => 101, 'name' => 'Jakarta', 'is_correct' => false],
            ['id' => 102, 'name' => 'Nusantara', 'is_correct' => true],
        ]);

        $grader = GraderFactory::make('single_choice');
        $result = $grader->grade($question, 'nusantara');

        $this->assertEquals(10.0, $result->pointsEarned);
        $this->assertTrue($result->hasPassed);
        $this->assertEquals('correct', $result->resultStatus);
        $this->assertEquals(['selected' => [102]], $result->answerData);
    }

    public function test_single_choice_grading_wrong(): void
    {
        $question = $this->makeQuestion('single_choice', 10, [
            ['id' => 101, 'name' => 'Opsi A', 'is_correct' => true],
            ['id' => 102, 'name' => 'Opsi B', 'is_correct' => false],
        ]);

        $grader = GraderFactory::make('single_choice');
        $result = $grader->grade($question, 102);

        $this->assertEquals(0.0, $result->pointsEarned);
        $this->assertFalse($result->hasPassed);
        $this->assertEquals('wrong', $result->resultStatus);
    }

    public function test_multiple_answer_grading_full_score(): void
    {
        $question = $this->makeQuestion('multiple_answer', 6, [
            ['id' => 201, 'name' => 'A (Benar)', 'is_correct' => true],
            ['id' => 202, 'name' => 'B (Salah)', 'is_correct' => false],
            ['id' => 203, 'name' => 'C (Benar)', 'is_correct' => true],
            ['id' => 204, 'name' => 'D (Benar)', 'is_correct' => true],
        ]);

        $grader = GraderFactory::make('multiple_answer');
        $result = $grader->grade($question, [201, 203, 204]);

        $this->assertEquals(6.0, $result->pointsEarned);
        $this->assertTrue($result->hasPassed);
        $this->assertEquals('correct', $result->resultStatus);
    }

    public function test_multiple_answer_grading_partial_without_penalty(): void
    {
        $question = $this->makeQuestion('multiple_answer', 6, [
            ['id' => 201, 'name' => 'A (Benar)', 'is_correct' => true],
            ['id' => 202, 'name' => 'B (Salah)', 'is_correct' => false],
            ['id' => 203, 'name' => 'C (Benar)', 'is_correct' => true],
            ['id' => 204, 'name' => 'D (Benar)', 'is_correct' => true],
        ]);

        $grader = GraderFactory::make('multiple_answer');
        // Student picks 2 correct out of 3, with 0 wrong: (2/3) * 6 = 4.0
        $result = $grader->grade($question, [201, 203]);

        $this->assertEquals(4.0, $result->pointsEarned);
        $this->assertTrue($result->hasPassed);
        $this->assertEquals('partial', $result->resultStatus);
    }

    public function test_multiple_answer_grading_partial_with_one_wrong_choice(): void
    {
        $question = $this->makeQuestion('multiple_answer', 6, [
            ['id' => 201, 'name' => 'A (Benar)', 'is_correct' => true],
            ['id' => 202, 'name' => 'B (Salah)', 'is_correct' => false],
            ['id' => 203, 'name' => 'C (Benar)', 'is_correct' => true],
            ['id' => 204, 'name' => 'D (Benar)', 'is_correct' => true],
        ]);

        $grader = GraderFactory::make('multiple_answer');
        // Student picks 2 correct and 1 wrong: net (2 - 1) = 1; (1/3) * 6 = 2.0
        $result = $grader->grade($question, [201, 203, 202]);

        $this->assertEquals(2.0, $result->pointsEarned);
        $this->assertTrue($result->hasPassed);
        $this->assertEquals('partial', $result->resultStatus);
    }

    public function test_multiple_answer_grading_cannot_be_negative(): void
    {
        $question = $this->makeQuestion('multiple_answer', 6, [
            ['id' => 201, 'name' => 'A (Benar)', 'is_correct' => true],
            ['id' => 202, 'name' => 'B (Salah)', 'is_correct' => false],
            ['id' => 203, 'name' => 'C (Salah)', 'is_correct' => false],
        ]);

        $grader = GraderFactory::make('multiple_answer');
        // Student picks 0 correct and 2 wrong: net max(0, 0 - 2) = 0
        $result = $grader->grade($question, [202, 203]);

        $this->assertEquals(0.0, $result->pointsEarned);
        $this->assertFalse($result->hasPassed);
        $this->assertEquals('wrong', $result->resultStatus);
    }

    public function test_true_false_table_grading(): void
    {
        $question = $this->makeQuestion('true_false_table', 4, [
            ['id' => 301, 'name' => 'Pernyataan 1', 'is_correct' => true],
            ['id' => 302, 'name' => 'Pernyataan 2', 'is_correct' => false],
            ['id' => 303, 'name' => 'Pernyataan 3', 'is_correct' => true],
            ['id' => 304, 'name' => 'Pernyataan 4', 'is_correct' => false],
        ]);

        $grader = GraderFactory::make('true_false_table');

        // All 4 correct
        $resultAll = $grader->grade($question, [
            301 => true,
            302 => false,
            303 => true,
            304 => false,
        ]);
        $this->assertEquals(4.0, $resultAll->pointsEarned);
        $this->assertTrue($resultAll->hasPassed);
        $this->assertEquals('correct', $resultAll->resultStatus);

        // 3 out of 4 correct: (3/4) * 4 = 3.0
        $result3 = $grader->grade($question, [
            301 => true,
            302 => true, // wrong choice (key is false)
            303 => true,
            304 => false,
        ]);
        $this->assertEquals(3.0, $result3->pointsEarned);
        $this->assertTrue($result3->hasPassed);
        $this->assertEquals('partial', $result3->resultStatus);

        // 0 correct: (0/4) * 4 = 0.0
        $result0 = $grader->grade($question, [
            301 => false,
            302 => true,
            303 => false,
            304 => true,
        ]);
        $this->assertEquals(0.0, $result0->pointsEarned);
        $this->assertFalse($result0->hasPassed);
        $this->assertEquals('wrong', $result0->resultStatus);
    }

    public function test_essay_grading(): void
    {
        $question = $this->makeQuestion('essay', 10, []);
        $grader = GraderFactory::make('essay');

        $resultFilled = $grader->grade($question, 'Jawaban panjang siswa');
        $this->assertEquals(0.0, $resultFilled->pointsEarned);
        $this->assertTrue($resultFilled->hasPassed);
        $this->assertEquals('pending', $resultFilled->resultStatus);

        $resultEmpty = $grader->grade($question, '   ');
        $this->assertEquals(0.0, $resultEmpty->pointsEarned);
        $this->assertFalse($resultEmpty->hasPassed);
        $this->assertEquals('pending', $resultEmpty->resultStatus);
    }
}
