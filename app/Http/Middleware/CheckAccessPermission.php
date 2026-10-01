<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\SubjectExam;
use App\Models\ClassStudent;

class CheckAccessPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Manager has full access
        if ($user->hasRole('manager')) {
            return $next($request);
        }
        
        // Teacher access control
        if ($user->hasRole('teacher')) {
            return $this->checkTeacherAccess($request, $next, $user);
        }
        
        // Student access control
        if ($user->hasRole('student')) {
            return $this->checkStudentAccess($request, $next, $user);
        }
        
        // If no role matches, deny access
        return response()->json([
            'success' => false,
            'message' => 'Access denied'
        ], 403);
    }
    
    /**
     * Check if teacher has access to the requested resource
     */
    private function checkTeacherAccess(Request $request, Closure $next, $user): Response
    {
        $route = $request->route();
        $parameters = $route->parameters();
        $uri = $request->route()->uri();
        
        // Check subject access (teacher can only access their assigned subjects)
        if (str_contains($uri, 'subjects/{id}')) {
            $subjectId = $parameters['id'];
            if (!$this->teacherHasSubjectAccess($user->id, $subjectId)) {
                return $this->accessDeniedResponse('You do not have access to this subject');
            }
        }
        
        // Check exam access (teacher can only access exams from their subjects)
        if (str_contains($uri, 'subject-exams/{id}')) {
            $examId = $parameters['id'];
            if (!$this->teacherHasExamAccess($user->id, $examId)) {
                return $this->accessDeniedResponse('You do not have access to this exam');
            }
        }
        
        // For classroom and topic access, teachers have full access
        // (they can view any classroom/topic for educational purposes)
        
        return $next($request);
    }
    
    /**
     * Check if student has access to the requested resource
     */
    private function checkStudentAccess(Request $request, Closure $next, $user): Response
    {
        $route = $request->route();
        $routeName = $route->getName();
        $parameters = $route->parameters();
        
        // Get the route pattern to determine resource type
        $uri = $request->route()->uri();
        
        // Check classroom access
        if (str_contains($uri, 'class-rooms/{id}')) {
            $classRoomId = $parameters['id'];
            if (!$this->studentHasClassRoomAccess($user->id, $classRoomId)) {
                return $this->accessDeniedResponse('You are not enrolled in this classroom');
            }
        }
        
        // Check subject access (student can only access subjects in their classrooms)
        if (str_contains($uri, 'subjects/{id}')) {
            $subjectId = $parameters['id'];
            if (!$this->studentHasSubjectAccess($user->id, $subjectId)) {
                return $this->accessDeniedResponse('You do not have access to this subject');
            }
        }
        
        // Check topic access (student can only access topics of subjects in their classrooms)
        if (str_contains($uri, 'topics/{id}')) {
            $topicId = $parameters['id'];
            if (!$this->studentHasTopicAccess($user->id, $topicId)) {
                return $this->accessDeniedResponse('You do not have access to this topic');
            }
        }
        
        // Check exam access (student can only access exams from subjects in their classrooms)
        if (str_contains($uri, 'subject-exams/{id}')) {
            $examId = $parameters['id'];
            if (!$this->studentHasExamAccess($user->id, $examId)) {
                return $this->accessDeniedResponse('You do not have access to this exam');
            }
        }
        
        return $next($request);
    }
    
    /**
     * Check if student is enrolled in the classroom
     */
    private function studentHasClassRoomAccess(int $studentId, int $classRoomId): bool
    {
        return ClassStudent::where('student_id', $studentId)
            ->where('class_room_id', $classRoomId)
            ->exists();
    }
    
    /**
     * Check if student has access to subject (subject is taught in student's classroom)
     */
    private function studentHasSubjectAccess(int $studentId, int $subjectId): bool
    {
        return ClassStudent::where('student_id', $studentId)
            ->whereHas('classRoom.classSubjects', function($query) use ($subjectId) {
                $query->where('subject_id', $subjectId);
            })
            ->exists();
    }
    
    /**
     * Check if student has access to topic (topic has subjects in student's classrooms)
     */
    private function studentHasTopicAccess(int $studentId, int $topicId): bool
    {
        return ClassStudent::where('student_id', $studentId)
            ->whereHas('classRoom.classSubjects.subject', function($query) use ($topicId) {
                $query->where('topic_id', $topicId);
            })
            ->exists();
    }
    
    /**
     * Check if student has access to exam (exam is from subject in student's classroom)
     */
    private function studentHasExamAccess(int $studentId, int $examId): bool
    {
        return ClassStudent::where('student_id', $studentId)
            ->whereHas('classRoom.classSubjects.subject.subjectExams', function($query) use ($examId) {
                $query->where('id', $examId);
            })
            ->exists();
    }
    
    /**
     * Check if teacher has access to subject (subject is assigned to teacher)
     */
    private function teacherHasSubjectAccess(int $teacherId, int $subjectId): bool
    {
        return Subject::where('id', $subjectId)
            ->where('teacher_id', $teacherId)
            ->exists();
    }
    
    /**
     * Check if teacher has access to exam (exam is from teacher's subject)
     */
    private function teacherHasExamAccess(int $teacherId, int $examId): bool
    {
        return SubjectExam::where('id', $examId)
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->exists();
    }
    
    /**
     * Return access denied response
     */
    private function accessDeniedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }
}