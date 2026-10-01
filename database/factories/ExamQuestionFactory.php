<?php

namespace Database\Factories;

use App\Models\SubjectExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamQuestion>
 */
class ExamQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_exam_id' => SubjectExam::factory(),
            'name' => $this->faker->sentence() . ' ' . uniqid() . mt_rand(1000, 9999),
            'timer' => $this->faker->numberBetween(60, 1800), // 1-30 minutes
            'type' => $this->faker->randomElement(['multiple_choice', 'essay']),
            'points' => $this->faker->numberBetween(1, 10),
        ];
    }
}
