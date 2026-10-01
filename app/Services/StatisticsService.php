<?php

namespace App\Services;

use App\Repositories\StatisticsRepository;

class StatisticsService
{
    private StatisticsRepository $statisticsRepository;

    public function __construct(StatisticsRepository $statisticsRepository)
    {
        $this->statisticsRepository = $statisticsRepository;
    }

    /**
     * Get statistics for requested entities
     */
    public function getStatistics(array $entityList): array
    {
        $statistics = [];
        
        foreach ($entityList as $entity) {
            switch ($entity) {
                case 'users':
                    $statistics['users_total'] = $this->statisticsRepository->getUsersCount();
                    break;
                case 'teachers':
                    $statistics['teachers_total'] = $this->statisticsRepository->getTeachersCount();
                    break;
                case 'students':
                    $statistics['students_total'] = $this->statisticsRepository->getStudentsCount();
                    break;
                case 'managers':
                    $statistics['managers_total'] = $this->statisticsRepository->getManagersCount();
                    break;
                case 'topics':
                    $statistics['topics_total'] = $this->statisticsRepository->getTopicsCount();
                    break;
                case 'subjects':
                    $statistics['subjects_total'] = $this->statisticsRepository->getSubjectsCount();
                    break;
                case 'class_rooms':
                    $statistics['class_rooms_total'] = $this->statisticsRepository->getClassRoomsCount();
                    break;
                case 'class_students':
                    $statistics['class_students_total'] = $this->statisticsRepository->getClassStudentsCount();
                    break;
                case 'class_subjects':
                    $statistics['class_subjects_total'] = $this->statisticsRepository->getClassSubjectsCount();
                    break;
                case 'subject_exams':
                    $statistics['subject_exams_total'] = $this->statisticsRepository->getSubjectExamsCount();
                    break;
                case 'exam_questions':
                    $statistics['exam_questions_total'] = $this->statisticsRepository->getExamQuestionsCount();
                    break;
                case 'question_options':
                    $statistics['question_options_total'] = $this->statisticsRepository->getQuestionOptionsCount();
                    break;
                case 'question_answers':
                    $statistics['question_answers_total'] = $this->statisticsRepository->getQuestionAnswersCount();
                    break;
                case 'exam_attempts':
                    $statistics['exam_attempts_total'] = $this->statisticsRepository->getExamAttemptsCount();
                    break;
                case 'classroom_students':
                    $statistics['classroom_students_total'] = $this->statisticsRepository->getClassroomStudentsCount();
                    break;
            }
        }
        
        return $statistics;
    }
}