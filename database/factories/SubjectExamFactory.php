<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubjectExam>
 */
class SubjectExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, $startDate->format('Y-m-d') . ' +2 weeks');
        
        return [
            'subject_id' => Subject::factory(),
            'name' => 'Exam ' . $this->faker->words(3, true) . ' ' . uniqid() . mt_rand(1000, 9999),
            'about' => $this->faker->paragraph(2),
            'total_points' => $this->faker->numberBetween(50, 200),
            'started_at' => $startDate->format('Y-m-d'),
            'ended_at' => $endDate->format('Y-m-d'),
        ];
    }
}
