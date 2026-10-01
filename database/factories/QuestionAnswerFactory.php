<?php

namespace Database\Factories;

use App\Models\ExamQuestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionAnswer>
 */
class QuestionAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maxPoints = 10;
        $hasPassed = $this->faker->boolean(70); // 70% pass rate
        
        return [
            'exam_question_id' => ExamQuestion::factory(),
            'student_id' => User::factory(),
            'has_passed' => $hasPassed,
            'points_earned' => $hasPassed ? $this->faker->numberBetween(6, $maxPoints) : $this->faker->numberBetween(0, 5),
        ];
    }
}
