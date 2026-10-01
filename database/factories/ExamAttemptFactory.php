<?php

namespace Database\Factories;

use App\Models\SubjectExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuestions = $this->faker->numberBetween(10, 50);
        $answeredQuestions = $this->faker->numberBetween(5, $totalQuestions);
        $isCompleted = $this->faker->boolean(80); // 80% completion rate
        
        $totalPoints = $this->faker->numberBetween(50, 200);
        $pointsEarned = $isCompleted ? 
            $this->faker->numberBetween(20, $totalPoints) : 
            $this->faker->numberBetween(0, (int)($totalPoints * 0.7));
        
        $hasPassed = $totalPoints > 0 ? ($pointsEarned / $totalPoints) >= 0.6 : false;
        
        return [
            'student_id' => User::factory(),
            'subject_exam_id' => SubjectExam::factory(),
            'is_completed' => $isCompleted,
            'total_questions' => $totalQuestions,
            'answered_questions' => $isCompleted ? $totalQuestions : $answeredQuestions,
            'total_points' => $totalPoints,
            'points_earned' => $pointsEarned,
            'has_passed' => $hasPassed,
            'completed_at' => $isCompleted ? $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s') : $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
        ];
    }
}
