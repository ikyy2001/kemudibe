<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Topic;
use App\Models\Subject;
use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\SubjectExam;
use App\Models\ExamQuestion;
use App\Models\QuestionOption;
use App\Models\QuestionAnswer;
use App\Models\ExamAttempt;

class StatisticsRepository
{
    /**
     * Get total users count
     */
    public function getUsersCount(): int
    {
        return User::count();
    }

    /**
     * Get teachers count
     */
    public function getTeachersCount(): int
    {
        return User::whereHas('roles', function($query) {
            $query->where('name', 'teacher');
        })->count();
    }

    /**
     * Get students count
     */
    public function getStudentsCount(): int
    {
        return User::whereHas('roles', function($query) {
            $query->where('name', 'student');
        })->count();
    }

    /**
     * Get managers count
     */
    public function getManagersCount(): int
    {
        return User::whereHas('roles', function($query) {
            $query->where('name', 'manager');
        })->count();
    }

    /**
     * Get topics count
     */
    public function getTopicsCount(): int
    {
        return Topic::count();
    }

    /**
     * Get subjects count
     */
    public function getSubjectsCount(): int
    {
        return Subject::count();
    }

    /**
     * Get classrooms count
     */
    public function getClassRoomsCount(): int
    {
        return ClassRoom::count();
    }

    /**
     * Get class students count
     */
    public function getClassStudentsCount(): int
    {
        return ClassStudent::count();
    }

    /**
     * Get class subjects count
     */
    public function getClassSubjectsCount(): int
    {
        return ClassSubject::count();
    }

    /**
     * Get subject exams count
     */
    public function getSubjectExamsCount(): int
    {
        return SubjectExam::count();
    }

    /**
     * Get exam questions count
     */
    public function getExamQuestionsCount(): int
    {
        return ExamQuestion::count();
    }

    /**
     * Get question options count
     */
    public function getQuestionOptionsCount(): int
    {
        return QuestionOption::count();
    }

    /**
     * Get question answers count
     */
    public function getQuestionAnswersCount(): int
    {
        return QuestionAnswer::count();
    }

    /**
     * Get exam attempts count
     */
    public function getExamAttemptsCount(): int
    {
        return ExamAttempt::count();
    }

    /**
     * Get classroom students count (alias for class students)
     */
    public function getClassroomStudentsCount(): int
    {
        return ClassStudent::count();
    }
}