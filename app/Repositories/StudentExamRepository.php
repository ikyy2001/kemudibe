<?php

namespace App\Repositories;

use App\Models\SubjectExam;
use App\Models\ExamAttempt;
use App\Models\QuestionAnswer;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use Illuminate\Database\Eloquent\Collection;

class StudentExamRepository
{
    /**
     * Get exam with questions and relationships
     */
    public function getExamWithQuestions(int $examId): SubjectExam
    {
        return SubjectExam::with(['subject', 'examQuestions.questionOptions'])
            ->findOrFail($examId);
    }

    /**
     * Check if student has access to exam through classroom enrollment
     */
    public function hasExamAccess(int $studentId, int $examId): bool
    {
        $exam = SubjectExam::with('subject')->find($examId);
        if (!$exam) {
            return false;
        }

        // Get student's enrolled classrooms
        $enrolledClassrooms = ClassStudent::where('student_id', $studentId)
            ->pluck('class_room_id');

        // Get classrooms that have this subject
        $subjectClassrooms = ClassSubject::where('subject_id', $exam->subject_id)
            ->pluck('class_room_id');

        // Check if there's any intersection
        return $enrolledClassrooms->intersect($subjectClassrooms)->count() > 0;
    }

    /**
     * Get student's exam attempt for specific exam
     */
    public function getStudentAttempt(int $studentId, int $examId): ?ExamAttempt
    {
        return ExamAttempt::where('student_id', $studentId)
            ->where('subject_exam_id', $examId)
            ->first();
    }

    /**
     * Get student's completed attempt for specific exam
     */
    public function getCompletedAttempt(int $studentId, int $examId): ?ExamAttempt
    {
        return ExamAttempt::where('student_id', $studentId)
            ->where('subject_exam_id', $examId)
            ->where('is_completed', true)
            ->first();
    }

    /**
     * Get student answers for specific exam
     */
    public function getStudentAnswers(int $studentId, int $examId): Collection
    {
        return QuestionAnswer::whereHas('examQuestion', function($query) use ($examId) {
            $query->where('subject_exam_id', $examId);
        })->where('student_id', $studentId)
          ->with('examQuestion')
          ->orderBy('exam_question_id')
          ->get();
    }

    /**
     * Get student answers with question options for specific exam
     */
    public function getStudentAnswersWithOptions(int $studentId, int $examId): Collection
    {
        return QuestionAnswer::whereHas('examQuestion', function($query) use ($examId) {
            $query->where('subject_exam_id', $examId);
        })->where('student_id', $studentId)
          ->with(['examQuestion.questionOptions'])
          ->orderBy('exam_question_id')
          ->get();
    }

    /**
     * Get count of answered questions for student in exam
     */
    public function getAnsweredCount(int $studentId, int $examId): int
    {
        return QuestionAnswer::whereHas('examQuestion', function($query) use ($examId) {
            $query->where('subject_exam_id', $examId);
        })->where('student_id', $studentId)->count();
    }
}