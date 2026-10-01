<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SubjectExam;
use App\Models\ExamAttempt;
use App\Models\QuestionAnswer;
use App\Models\ExamQuestion;
use App\Models\QuestionOption;

class ExamAnswersSeeder extends Seeder
{
    public function run()
    {
        // Get exam 4013
        $exam = SubjectExam::with('examQuestions.questionOptions')->find(4013);
        
        if (!$exam) {
            $this->command->info('Exam 4013 not found');
            return;
        }

        $this->command->info("Creating answers for exam: {$exam->name}");
        
        // Get some students (take first 10 students with role 'student')
        $students = User::role('student')->take(10)->get();
        
        if ($students->count() === 0) {
            $this->command->info('No students found');
            return;
        }

        $this->command->info("Found {$students->count()} students");

        // Create exam attempts and answers for each student
        foreach ($students as $student) {
            // Check if exam attempt already exists
            $examAttempt = ExamAttempt::where('student_id', $student->id)
                ->where('subject_exam_id', $exam->id)
                ->first();
                
            if (!$examAttempt) {
                // Create exam attempt for this student
                $examAttempt = ExamAttempt::create([
                    'student_id' => $student->id,
                    'subject_exam_id' => $exam->id,
                    'is_completed' => true,
                    'total_questions' => $exam->examQuestions->count(),
                    'answered_questions' => $exam->examQuestions->count(),
                    'completed_at' => now()->subDays(rand(1, 30)), // Random completion date
                ]);
            }

            $totalPointsEarned = 0;

            // Skip if answers already exist for this student
            $existingAnswers = QuestionAnswer::where('student_id', $student->id)
                ->whereHas('examQuestion', function($query) use ($exam) {
                    $query->where('subject_exam_id', $exam->id);
                })
                ->count();
                
            if ($existingAnswers > 0) {
                $this->command->info("Answers already exist for student: {$student->name}, skipping...");
                continue;
            }

            // Create answers for each question
            foreach ($exam->examQuestions as $question) {
                $pointsEarned = 0;
                $answerText = '';
                $hasPassed = false;

                if ($question->type === 'multiple_choice') {
                    // For multiple choice, randomly select an option
                    $selectedOption = $question->questionOptions->random();
                    $answerText = $selectedOption->option_letter;
                    
                    // Check if the selected option is correct
                    if ($selectedOption->is_correct) {
                        $pointsEarned = $question->points;
                        $hasPassed = true;
                    } else {
                        // Give partial credit sometimes (70% chance of getting it wrong completely)
                        if (rand(1, 100) > 70) {
                            $pointsEarned = $question->points * 0.5; // 50% partial credit
                        }
                    }
                } else {
                    // For essay questions, create varied responses
                    $essayResponses = [
                        "This is a comprehensive analysis of the topic. The key themes include literary symbolism, character development, and narrative structure. The author effectively uses various literary devices to convey deeper meaning.",
                        "In my opinion, this work represents a significant contribution to American literature. The themes explored are universal and timeless, dealing with human nature and society.",
                        "The literary techniques employed by the author create a rich tapestry of meaning. Through careful analysis, we can see how the work reflects the social and cultural context of its time.",
                        "This piece demonstrates masterful storytelling through its use of metaphor, imagery, and character development. The author's style is both engaging and thought-provoking.",
                        "The work presents complex themes that require careful consideration. The author's approach to character development and plot structure creates a compelling narrative.",
                        "I believe this work stands as an important example of American literary tradition. The themes and techniques used by the author continue to resonate with modern readers.",
                        "The literary elements in this work combine to create a powerful and moving piece. The author's use of symbolism and imagery enhances the overall impact of the narrative.",
                        "This analysis reveals the depth and complexity of the work. The author's skill in crafting memorable characters and compelling dialogue is evident throughout.",
                        "The work demonstrates the author's mastery of literary form and content. Through careful examination, we can appreciate the various layers of meaning present in the text.",
                        "In conclusion, this piece represents a significant achievement in literature. The author's innovative approach and skillful execution make it a work of lasting importance."
                    ];
                    
                    $answerText = $essayResponses[array_rand($essayResponses)];
                    
                    // Essay grading - random but realistic distribution
                    $gradePercentage = rand(60, 95); // 60-95% range for essay answers
                    $pointsEarned = ($gradePercentage / 100) * $question->points;
                    $hasPassed = $gradePercentage >= 70; // Pass threshold at 70%
                }

                // Create the question answer
                QuestionAnswer::create([
                    'exam_question_id' => $question->id,
                    'student_id' => $student->id,
                    'answer_text' => $answerText,
                    'has_passed' => $hasPassed,
                    'points_earned' => $pointsEarned,
                ]);

                $totalPointsEarned += $pointsEarned;
            }

            $this->command->info("Created answers for student: {$student->name} (Total points: {$totalPointsEarned})");
        }

        $this->command->info('Exam answers seeder completed successfully!');
    }
}