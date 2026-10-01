<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassroomActivity;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClassroomActivityController extends Controller
{
    /**
     * Get all activities for a classroom (optionally filtered by type)
     */
    public function index(Request $request, int $classroomId)
    {
        try {
            $classRoom = ClassRoom::findOrFail($classroomId);

            $query = ClassroomActivity::where('class_room_id', $classroomId)
                ->with('creator:id,name,photo');

            if ($request->filled('type')) {
                $query->where('type', $request->string('type'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $activities = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $activities,
                'counts' => [
                    'all' => ClassroomActivity::where('class_room_id', $classroomId)->count(),
                    'challenges' => ClassroomActivity::where('class_room_id', $classroomId)->where('type', 'challenge')->count(),
                    'homeworks' => ClassroomActivity::where('class_room_id', $classroomId)->where('type', 'homework')->count(),
                    'others' => ClassroomActivity::where('class_room_id', $classroomId)->where('type', 'other')->count(),
                ]
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve classroom activities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a new activity for a classroom
     */
    public function store(Request $request, int $classroomId)
    {
        try {
            ClassRoom::findOrFail($classroomId);

            $validator = Validator::make($request->all(), [
                'type' => 'required|in:challenge,homework,other',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'difficulty' => 'nullable|string|in:Easy,Medium,Hard',
                'points' => 'nullable|integer|min:0',
                'status' => 'nullable|string',
                'due_date' => 'nullable|date',
                'attachment' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $activity = ClassroomActivity::create([
                'class_room_id' => $classroomId,
                'type' => $request->input('type'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'difficulty' => $request->input('difficulty', 'Medium'),
                'points' => $request->input('points', 100),
                'status' => $request->input('status', 'active'),
                'due_date' => $request->input('due_date'),
                'attachment' => $request->input('attachment'),
                'created_by' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => ucfirst($activity->type) . ' created successfully',
                'data' => $activity->load('creator:id,name,photo')
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Classroom not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create classroom activity',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Show single activity
     */
    public function show(int $classroomId, int $id)
    {
        try {
            $activity = ClassroomActivity::where('class_room_id', $classroomId)
                ->with(['classRoom:id,name', 'creator:id,name,photo'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $activity
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }
    }

    /**
     * Update activity
     */
    public function update(Request $request, int $classroomId, int $id)
    {
        try {
            $activity = ClassroomActivity::where('class_room_id', $classroomId)->findOrFail($id);

            $activity->update($request->only([
                'title',
                'description',
                'type',
                'difficulty',
                'points',
                'status',
                'due_date',
                'attachment',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Activity updated successfully',
                'data' => $activity->load('creator:id,name,photo')
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }
    }

    /**
     * Delete activity
     */
    public function destroy(int $classroomId, int $id)
    {
        try {
            $activity = ClassroomActivity::where('class_room_id', $classroomId)->findOrFail($id);
            $activity->delete();

            return response()->json([
                'success' => true,
                'message' => 'Activity deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }
    }
}
