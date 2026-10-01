<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\ClassroomActivity;
use App\Models\Project;
use App\Models\User;
use App\Models\SubjectExam;
use App\Models\ExamAttempt;
use Illuminate\Database\Seeder;

class ClassroomActivityAndProjectSeeder extends Seeder
{
    public function run(): void
    {
        $classroom = ClassRoom::first();
        if (!$classroom) {
            $classroom = ClassRoom::create([
                'name' => 'Ruang 1 SABIN',
                'grade' => 12,
            ]);
        }

        $teacher = User::role('teacher')->first() ?? User::first();
        $student = User::role('student')->first() ?? User::first();
        $manager = User::role('manager')->first() ?? User::first();

        // 1. Seed Challenges for the Classroom
        $challenges = [
            [
                'class_room_id' => $classroom->id,
                'type' => 'challenge',
                'title' => 'Math Sprint Challenge: Calculus Speedrun',
                'description' => 'Complete 10 differential equations in under 15 minutes with high precision. Leaderboard rankings will be showcased.',
                'difficulty' => 'Hard',
                'points' => 150,
                'status' => 'active',
                'due_date' => now()->addDays(5),
                'attachment' => null,
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'challenge',
                'title' => 'Algorithm Debugging Sprint',
                'description' => 'Analyze the broken code snippets, identify time complexity bottlenecks, and refactor for O(n) runtime.',
                'difficulty' => 'Medium',
                'points' => 100,
                'status' => 'active',
                'due_date' => now()->addDays(3),
                'attachment' => null,
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'challenge',
                'title' => 'Vocabulary & Grammar Mastery Sprint',
                'description' => 'Test your English advanced syntax and nuance comprehension against classroom peers.',
                'difficulty' => 'Easy',
                'points' => 80,
                'status' => 'completed',
                'due_date' => now()->subDay(),
                'attachment' => null,
                'created_by' => $teacher?->id,
            ],
        ];

        foreach ($challenges as $item) {
            ClassroomActivity::firstOrCreate(
                ['class_room_id' => $item['class_room_id'], 'title' => $item['title']],
                $item
            );
        }

        // 2. Seed Homeworks for the Classroom
        $homeworks = [
            [
                'class_room_id' => $classroom->id,
                'type' => 'homework',
                'title' => 'Weekly Problem Set #4: Integration by Parts',
                'description' => 'Solve problems 1 through 15 from Chapter 4. Ensure all steps of integration and substitution are written out clearly.',
                'difficulty' => 'Medium',
                'points' => 100,
                'status' => 'active',
                'due_date' => now()->addDays(4),
                'attachment' => 'https://example.com/homework_pset4.pdf',
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'homework',
                'title' => 'Physics Mechanics Lab Reflection Paper',
                'description' => 'Write a 2-page synthesis on pendulum motion dynamics, comparing experimental errors with theoretical calculations.',
                'difficulty' => 'Hard',
                'points' => 120,
                'status' => 'active',
                'due_date' => now()->addDays(7),
                'attachment' => 'https://example.com/physics_lab_template.pdf',
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'homework',
                'title' => 'Literary Analysis Essay: Modern Indonesian Prose',
                'description' => 'Analyze character development and socio-political themes in the assigned short story collection.',
                'difficulty' => 'Medium',
                'points' => 90,
                'status' => 'completed',
                'due_date' => now()->subDays(2),
                'attachment' => null,
                'created_by' => $teacher?->id,
            ],
        ];

        foreach ($homeworks as $item) {
            ClassroomActivity::firstOrCreate(
                ['class_room_id' => $item['class_room_id'], 'title' => $item['title']],
                $item
            );
        }

        // 3. Seed Others (Learning Resources & Materials) for Classroom
        $others = [
            [
                'class_room_id' => $classroom->id,
                'type' => 'other',
                'title' => 'Semester 1 Complete Syllabus & Exam Schedule',
                'description' => 'Official learning guidelines, grading criteria, and schedule of midterms and finals for Academic Year 2026/2027.',
                'difficulty' => 'Easy',
                'points' => 10,
                'status' => 'active',
                'due_date' => null,
                'attachment' => 'https://example.com/syllabus_2026.pdf',
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'other',
                'title' => 'Calculus Formula Sheet & Cheatsheet (PDF)',
                'description' => 'Comprehensive quick-reference table for common derivatives, standard integrals, and trigonometric substitutions.',
                'difficulty' => 'Easy',
                'points' => 20,
                'status' => 'active',
                'due_date' => null,
                'attachment' => 'https://example.com/calculus_cheatsheet.pdf',
                'created_by' => $teacher?->id,
            ],
            [
                'class_room_id' => $classroom->id,
                'type' => 'other',
                'title' => 'Interactive Science Lab Video Lecture Series',
                'description' => 'Supplementary video resources explaining laboratory safety protocol, measurement accuracy, and instrument setup.',
                'difficulty' => 'Easy',
                'points' => 30,
                'status' => 'active',
                'due_date' => null,
                'attachment' => 'https://example.com/lab_recordings.mp4',
                'created_by' => $teacher?->id,
            ],
        ];

        foreach ($others as $item) {
            ClassroomActivity::firstOrCreate(
                ['class_room_id' => $item['class_room_id'], 'title' => $item['title']],
                $item
            );
        }

        // 4. Seed Projects for Manager/Admin
        $projects = [
            [
                'title' => 'Digital Curriculum Transformation 2026',
                'description' => 'Transitioning all STEM lesson plans to interactive computer-based assessments and practical simulations.',
                'category' => 'Curriculum Innovation',
                'status' => 'in_progress',
                'progress' => 68,
                'class_room_id' => $classroom->id,
                'leader_id' => $teacher?->id ?? $manager?->id,
                'due_date' => now()->addMonths(2)->format('Y-m-d'),
            ],
            [
                'title' => 'Annual Science & Robotics Exhibition',
                'description' => 'Student innovation showcase featuring applied robotics, green energy models, and computational physics exhibits.',
                'category' => 'School Event',
                'status' => 'in_progress',
                'progress' => 45,
                'class_room_id' => $classroom->id,
                'leader_id' => $teacher?->id,
                'due_date' => now()->addMonth()->format('Y-m-d'),
            ],
            [
                'title' => 'Campus E-Learning Infrastructure Upgrade',
                'description' => 'High-speed local network and low-latency testing server deployment for standardized exam proctoring.',
                'category' => 'Infrastructure',
                'status' => 'completed',
                'progress' => 100,
                'class_room_id' => null,
                'leader_id' => $manager?->id,
                'due_date' => now()->subDays(10)->format('Y-m-d'),
            ],
            [
                'title' => 'Student Leadership & Community Outreach',
                'description' => 'Collaborative community tutoring program organized by senior students for regional primary schools.',
                'category' => 'Community',
                'status' => 'planning',
                'progress' => 15,
                'class_room_id' => $classroom->id,
                'leader_id' => $student?->id ?? $teacher?->id,
                'due_date' => now()->addMonths(3)->format('Y-m-d'),
            ],
        ];

        foreach ($projects as $proj) {
            Project::firstOrCreate(
                ['title' => $proj['title']],
                $proj
            );
        }

        // 5. Seed Exam Attempt / Grade if student has exam
        $exam = SubjectExam::first();
        if ($exam && $student) {
            ExamAttempt::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'subject_exam_id' => $exam->id,
                ],
                [
                    'total_attempts' => 1,
                    'is_completed' => true,
                    'total_questions' => 4,
                    'answered_questions' => 4,
                    'total_points' => 100,
                    'points_earned' => 88,
                    'has_passed' => true,
                    'completed_at' => now()->subHours(6),
                ]
            );
        }
    }
}
