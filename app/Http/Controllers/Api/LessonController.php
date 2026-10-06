<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    /**
     * Display a listing of lessons.
     */
    public function index(Request $request)
    {
        $query = Lesson::with(['subject', 'topic']);

        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->input('subject_id'));
        }

        if ($request->has('topic_id')) {
            $query->where('topic_id', $request->input('topic_id'));
        }

        $lessons = $query->orderBy('order', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }

    /**
     * Store a newly created lesson.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => ['required', \App\Rules\TenantRule::exists('subjects', 'id')],
            'topic_id' => ['nullable', \App\Rules\TenantRule::exists('topics', 'id')],
            'title' => 'required|string|max:255',
            'content_type' => 'required|in:text,file,drive,video',
            'text_content' => 'nullable|string',
            'attachment' => 'nullable|file|max:51200', // 50MB
            'drive_url' => 'nullable|url',
            'video_url' => 'nullable|url',
            'order' => 'nullable|integer',
        ]);

        $institution = app(\App\Services\InstitutionContext::class)->get();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileSize = $file->getSize();

            if ($institution && $institution->hasReachedStorageQuota($fileSize)) {
                return response()->json([
                    'message' => 'Batas kuota penyimpanan lembaga telah tercapai. Hubungi Super Admin.',
                    'code' => 'STORAGE_QUOTA_EXCEEDED'
                ], 422);
            }

            $storageDir = $institution ? "institutions/{$institution->id}/lessons" : "lessons";
            $path = $file->store($storageDir, 'public');
            $validated['attachment'] = $path;

            $institution?->recordStorageAdded($fileSize);
        }

        $lesson = Lesson::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil ditambahkan',
            'data' => $lesson->load(['subject', 'topic'])
        ], 201);
    }

    /**
     * Display the specified lesson.
     */
    public function show(int $id)
    {
        $lesson = Lesson::with(['subject', 'topic'])->find($id);

        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    /**
     * Update the specified lesson.
     */
    public function update(Request $request, int $id)
    {
        $lesson = Lesson::find($id);

        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'subject_id' => ['sometimes', \App\Rules\TenantRule::exists('subjects', 'id')],
            'topic_id' => ['nullable', \App\Rules\TenantRule::exists('topics', 'id')],
            'title' => 'sometimes|string|max:255',
            'content_type' => 'sometimes|in:text,file,drive,video',
            'text_content' => 'nullable|string',
            'attachment' => 'nullable|file|max:51200',
            'drive_url' => 'nullable|url',
            'video_url' => 'nullable|url',
            'order' => 'nullable|integer',
        ]);

        $institution = app(\App\Services\InstitutionContext::class)->get();

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileSize = $file->getSize();

            if ($institution && $institution->hasReachedStorageQuota($fileSize)) {
                return response()->json([
                    'message' => 'Batas kuota penyimpanan lembaga telah tercapai. Hubungi Super Admin.',
                    'code' => 'STORAGE_QUOTA_EXCEEDED'
                ], 422);
            }

            // Delete old attachment if exists & track storage
            $rawAttachment = $lesson->getRawOriginal('attachment');
            if ($rawAttachment && Storage::disk('public')->exists($rawAttachment)) {
                $oldSize = Storage::disk('public')->size($rawAttachment);
                Storage::disk('public')->delete($rawAttachment);
                $institution?->recordStorageFreed($oldSize);
            }

            $storageDir = $institution ? "institutions/{$institution->id}/lessons" : "lessons";
            $path = $file->store($storageDir, 'public');
            $validated['attachment'] = $path;

            $institution?->recordStorageAdded($fileSize);
        }

        $lesson->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil diperbarui',
            'data' => $lesson->load(['subject', 'topic'])
        ]);
    }

    /**
     * Remove the specified lesson.
     */
    public function destroy(int $id)
    {
        $lesson = Lesson::find($id);

        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Materi tidak ditemukan'
            ], 404);
        }

        $institution = app(\App\Services\InstitutionContext::class)->get();
        $rawAttachment = $lesson->getRawOriginal('attachment');
        if ($rawAttachment && Storage::disk('public')->exists($rawAttachment)) {
            $oldSize = Storage::disk('public')->size($rawAttachment);
            Storage::disk('public')->delete($rawAttachment);
            $institution?->recordStorageFreed($oldSize);
        }

        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => 'Materi berhasil dihapus'
        ]);
    }
}
