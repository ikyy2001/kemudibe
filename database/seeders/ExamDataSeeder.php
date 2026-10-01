<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\SubjectExam;
use App\Models\ExamQuestion;
use App\Models\QuestionOption;
use App\Models\QuestionAnswer;
use App\Models\User;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating exam data in batches...');
        
        // Create ClassStudents (student enrollments)
        $this->createClassStudents();
        
        // Create ClassSubjects (subject assignments to classrooms)
        $this->createClassSubjects();
        
        // Create SubjectExams
        $this->createSubjectExams();
        
        // Create ExamQuestions with Options
        $this->createExamQuestions();
        
        // Create QuestionAnswers (student responses)
        $this->createQuestionAnswers();
        
        $this->command->info('Exam data seeding completed successfully!');
    }
    
    private function createClassStudents(): void
    {
        $this->command->info('Creating class student enrollments...');
        
        $students = User::role('student')->pluck('id')->toArray();
        $classrooms = ClassRoom::pluck('id')->toArray();
        
        $batchSize = 1000;
        $totalRecords = 10000; // 10k enrollments (10x base 1000)
        
        for ($i = 0; $i < ceil($totalRecords / $batchSize); $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            $enrollments = [];
            for ($j = 0; $j < $currentBatchSize; $j++) {
                $enrollments[] = [
                    'student_id' => fake()->randomElement($students),
                    'class_room_id' => fake()->randomElement($classrooms),
                    'has_passed' => fake()->boolean(75),
                    'rapport' => fake()->randomElement([
                        'Excellent performance', 'Good progress', 'Average performance',
                        'Needs improvement', 'Outstanding student'
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DB::table('class_students')->insertOrIgnore($enrollments);
            $this->command->info("Class enrollments batch " . ($i + 1) . " completed");
        }
    }
    
    private function createClassSubjects(): void
    {
        $this->command->info('Creating class subject assignments...');
        
        $subjects = Subject::pluck('id')->toArray();
        $classrooms = ClassRoom::pluck('id')->toArray();
        
        $batchSize = 1000;
        $totalRecords = 5000; // 5k assignments (5x base 1000)
        
        for ($i = 0; $i < ceil($totalRecords / $batchSize); $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            $assignments = [];
            for ($j = 0; $j < $currentBatchSize; $j++) {
                $assignments[] = [
                    'class_room_id' => fake()->randomElement($classrooms),
                    'subject_id' => fake()->randomElement($subjects),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DB::table('class_subjects')->insertOrIgnore($assignments);
            $this->command->info("Class subjects batch " . ($i + 1) . " completed");
        }
    }
    
    private function createSubjectExams(): void
    {
        $this->command->info('Creating subject exams...');
        
        $subjects = Subject::pluck('id')->toArray();
        $batchSize = 500;
        $totalRecords = 3000; // 3k exams (3x base 1000)
        
        for ($i = 0; $i < ceil($totalRecords / $batchSize); $i++) {
            $currentBatchSize = min($batchSize, $totalRecords - ($i * $batchSize));
            
            $exams = SubjectExam::factory($currentBatchSize)->create([
                'subject_id' => fake()->randomElement($subjects),
            ]);
            $this->command->info("Subject exams batch " . ($i + 1) . " completed");
        }
    }
    
    private function createExamQuestions(): void
    {
        $this->command->info('Creating exam questions with options...');
        
        $exams = SubjectExam::pluck('id')->toArray();
        $batchSize = 500;
        $totalQuestions = 8000; // 8k questions (8x base 1000)
        
        for ($i = 0; $i < ceil($totalQuestions / $batchSize); $i++) {
            $currentBatchSize = min($batchSize, $totalQuestions - ($i * $batchSize));
            
            $questions = ExamQuestion::factory($currentBatchSize)->create([
                'subject_exam_id' => fake()->randomElement($exams),
            ]);
            
            // Create 4 options for each multiple choice question
            foreach ($questions as $question) {
                if ($question->type === 'multiple_choice') {
                    $options = [];
                    for ($j = 0; $j < 4; $j++) {
                        $options[] = [
                            'exam_question_id' => $question->id,
                            'is_correct' => $j === 0, // First option is correct
                            'name' => fake()->sentence(6),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DB::table('question_options')->insert($options);
                }
            }
            
            $this->command->info("Questions batch " . ($i + 1) . " completed");
            unset($questions);
            gc_collect_cycles();
        }
    }
    
    private function createQuestionAnswers(): void
    {
        $this->command->info('Creating question answers (student responses)...');
        
        $students = User::role('student')->pluck('id')->toArray();
        $questions = ExamQuestion::pluck('id')->toArray();
        $batchSize = 1000;
        $totalAnswers = 15000; // 15k student responses (15x base 1000)
        
        for ($i = 0; $i < ceil($totalAnswers / $batchSize); $i++) {
            $currentBatchSize = min($batchSize, $totalAnswers - ($i * $batchSize));
            
            $answers = [];
            for ($j = 0; $j < $currentBatchSize; $j++) {
                $hasPassed = fake()->boolean(70); // 70% pass rate
                $maxPoints = fake()->numberBetween(5, 10);
                
                $answers[] = [
                    'exam_question_id' => fake()->randomElement($questions),
                    'student_id' => fake()->randomElement($students),
                    'has_passed' => $hasPassed,
                    'points_earned' => $hasPassed ? fake()->numberBetween(6, $maxPoints) : fake()->numberBetween(0, 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DB::table('question_answers')->insertOrIgnore($answers);
            $this->command->info("Question answers batch " . ($i + 1) . " completed");
        }
    }
}
