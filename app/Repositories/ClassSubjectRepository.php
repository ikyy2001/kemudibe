<?php

namespace App\Repositories;

use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\ClassRoom;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClassSubjectRepository
{
    /**
     * Get paginated subject assignments
     */
    public function getPaginated(array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassSubject::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all subject assignments without pagination
     */
    public function getAll(array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->get();
    }

    /**
     * Find assignment by ID with full relationships
     */
    public function findWithRelations(int $id, array $fields = ['*']): ClassSubject
    {
        return ClassSubject::select($fields)
            ->with([
                'subject:id,name,photo,tagline,about,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->findOrFail($id);
    }

    /**
     * Create a new subject assignment
     */
    public function create(array $data): ClassSubject
    {
        return ClassSubject::create($data);
    }

    /**
     * Update assignment by ID
     */
    public function update(int $id, array $data): ClassSubject
    {
        $assignment = ClassSubject::findOrFail($id);
        $assignment->update($data);
        return $assignment->fresh();
    }

    /**
     * Delete assignment by ID
     */
    public function delete(int $id): bool
    {
        $assignment = ClassSubject::findOrFail($id);
        return $assignment->delete();
    }

    /**
     * Find assignments by classroom ID
     */
    public function findByClassRoom(int $classRoomId, array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->where('class_room_id', $classRoomId)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo'
            ])
            ->latest()
            ->get();
    }

    /**
     * Find assignments by subject ID
     */
    public function findBySubject(int $subjectId, array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->where('subject_id', $subjectId)
            ->with(['classRoom:id,name,photo,grade'])
            ->latest()
            ->get();
    }

    /**
     * Find assignments by teacher ID (through subject relationship)
     */
    public function findByTeacher(int $teacherId, array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->whereHas('subject', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->get();
    }

    /**
     * Find assignments by topic ID (through subject relationship)
     */
    public function findByTopic(int $topicId, array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->whereHas('subject', function($query) use ($topicId) {
                $query->where('topic_id', $topicId);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->get();
    }

    /**
     * Find assignments by grade level
     */
    public function findByGrade(int $grade, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassSubject::select($fields)
            ->whereHas('classRoom', function($query) use ($grade) {
                $query->where('grade', $grade);
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Check if subject is already assigned to classroom
     */
    public function isSubjectAssigned(int $classRoomId, int $subjectId): bool
    {
        return ClassSubject::where('class_room_id', $classRoomId)
            ->where('subject_id', $subjectId)
            ->exists();
    }

    /**
     * Find specific assignment by classroom and subject
     */
    public function findByClassRoomAndSubject(int $classRoomId, int $subjectId): ?ClassSubject
    {
        return ClassSubject::where('class_room_id', $classRoomId)
            ->where('subject_id', $subjectId)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->first();
    }

    /**
     * Search assignments by subject name or classroom name
     */
    public function searchBySubjectOrClassRoom(string $query, array $fields = ['*'], int $perPage = 10): LengthAwarePaginator
    {
        return ClassSubject::select($fields)
            ->whereHas('subject', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('tagline', 'LIKE', "%{$query}%");
            })
            ->orWhereHas('classRoom', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            })
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search assignments with pagination for frontend modal
     */
    public function searchWithPagination(string $query, array $fields = ['*'], int $page = 1, int $perPage = 10): array
    {
        $queryBuilder = ClassSubject::select($fields)
            ->with([
                'subject:id,name,photo,tagline,teacher_id',
                'subject.teacher:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->latest();

        if (!empty($query)) {
            $queryBuilder->whereHas('subject', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('tagline', 'LIKE', "%{$query}%");
            })
            ->orWhereHas('classRoom', function($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%");
            });
        }

        $total = $queryBuilder->count();

        $assignments = $queryBuilder->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $assignments,
            'total' => $total,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $total > ($page * $perPage)
        ];
    }

    /**
     * Find multiple assignments by IDs
     */
    public function findManyByIds(array $ids, array $fields = ['*']): Collection
    {
        return ClassSubject::select($fields)
            ->whereIn('id', $ids)
            ->with([
                'subject:id,name,photo,tagline,teacher_id,topic_id',
                'subject.teacher:id,name,email,photo',
                'subject.topic:id,name,photo',
                'classRoom:id,name,photo,grade'
            ])
            ->get();
    }

    /**
     * Get available subjects for classroom (not yet assigned)
     */
    public function getAvailableSubjectsForClassRoom(int $classRoomId): Collection
    {
        return Subject::whereDoesntHave('classSubjects', function($query) use ($classRoomId) {
                $query->where('class_room_id', $classRoomId);
            })
            ->with(['teacher:id,name,email,photo', 'topic:id,name,photo'])
            ->select(['id', 'name', 'photo', 'content', 'tagline', 'teacher_id', 'topic_id'])
            ->latest()
            ->get();
    }

    /**
     * Get available classrooms for subject (not yet assigned)
     */
    public function getAvailableClassRoomsForSubject(int $subjectId): Collection
    {
        return ClassRoom::whereDoesntHave('classSubjects', function($query) use ($subjectId) {
                $query->where('subject_id', $subjectId);
            })
            ->select(['id', 'name', 'photo', 'grade'])
            ->latest()
            ->get();
    }

    /**
     * Bulk assign subjects to classroom
     */
    public function bulkAssignToClassRoom(int $classRoomId, array $subjectIds): array
    {
        $assignments = [];
        foreach ($subjectIds as $subjectId) {
            // Check if already assigned
            if (!$this->isSubjectAssigned($classRoomId, $subjectId)) {
                $assignments[] = $this->create([
                    'class_room_id' => $classRoomId,
                    'subject_id' => $subjectId,
                ]);
            }
        }
        return $assignments;
    }

    /**
     * Bulk assign classrooms to subject
     */
    public function bulkAssignToSubject(int $subjectId, array $classRoomIds): array
    {
        $assignments = [];
        foreach ($classRoomIds as $classRoomId) {
            // Check if already assigned
            if (!$this->isSubjectAssigned($classRoomId, $subjectId)) {
                $assignments[] = $this->create([
                    'class_room_id' => $classRoomId,
                    'subject_id' => $subjectId,
                ]);
            }
        }
        return $assignments;
    }

    /**
     * Get assignment statistics for classroom
     */
    public function getClassRoomStatistics(int $classRoomId): array
    {
        $totalSubjects = ClassSubject::where('class_room_id', $classRoomId)->count();
        $uniqueTeachers = ClassSubject::where('class_room_id', $classRoomId)
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->distinct('subjects.teacher_id')
            ->count('subjects.teacher_id');
        $uniqueTopics = ClassSubject::where('class_room_id', $classRoomId)
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->distinct('subjects.topic_id')
            ->count('subjects.topic_id');

        return [
            'total_subjects' => $totalSubjects,
            'unique_teachers' => $uniqueTeachers,
            'unique_topics' => $uniqueTopics
        ];
    }

    /**
     * Get assignment statistics for subject
     */
    public function getSubjectStatistics(int $subjectId): array
    {
        $totalClassrooms = ClassSubject::where('subject_id', $subjectId)->count();
        $gradeDistribution = ClassSubject::where('subject_id', $subjectId)
            ->join('class_rooms', 'class_subjects.class_room_id', '=', 'class_rooms.id')
            ->groupBy('class_rooms.grade')
            ->selectRaw('class_rooms.grade, COUNT(*) as count')
            ->get()
            ->pluck('count', 'grade')
            ->toArray();

        return [
            'total_classrooms' => $totalClassrooms,
            'grade_distribution' => $gradeDistribution
        ];
    }

    /**
     * Get classrooms assigned to teacher via their subjects
     */
    public function getTeacherClassRooms(int $teacherId): Collection
    {
        return ClassRoom::select(['class_rooms.id', 'class_rooms.name', 'class_rooms.photo', 'class_rooms.grade'])
            ->join('class_subjects', 'class_rooms.id', '=', 'class_subjects.class_room_id')
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->where('subjects.teacher_id', $teacherId)
            ->distinct()
            ->withCount(['classStudents', 'classSubjects'])
            ->get();
    }
}
