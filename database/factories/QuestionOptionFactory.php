<?php

namespace Database\Factories;

use App\Models\ExamQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuestionOption>
 */
class QuestionOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_question_id' => ExamQuestion::factory(),
            'is_correct' => $this->faker->boolean(25), // 25% chance of being correct
            'name' => $this->faker->sentence(6),
        ];
    }
}
