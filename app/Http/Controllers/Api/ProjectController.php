<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects
     */
    public function index(Request $request)
    {
        try {
            $query = Project::with(['classRoom:id,name', 'leader:id,name,photo']);

            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }

            if ($request->filled('category')) {
                $query->where('category', $request->string('category'));
            }

            if ($request->filled('search')) {
                $search = $request->string('search');
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }

            $projects = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $projects,
                'stats' => [
                    'total' => Project::count(),
                    'in_progress' => Project::where('status', 'in_progress')->count(),
                    'completed' => Project::where('status', 'completed')->count(),
                    'planning' => Project::where('status', 'planning')->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created project
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'nullable|string|max:100',
                'status' => 'nullable|in:planning,in_progress,completed,on_hold',
                'progress' => 'nullable|integer|min:0|max:100',
                'class_room_id' => 'nullable|exists:class_rooms,id',
                'leader_id' => 'nullable|exists:users,id',
                'due_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $project = Project::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'category' => $request->input('category', 'Academic'),
                'status' => $request->input('status', 'in_progress'),
                'progress' => $request->input('progress', 0),
                'class_room_id' => $request->input('class_room_id'),
                'leader_id' => $request->input('leader_id') ?? $request->user()?->id,
                'due_date' => $request->input('due_date'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project->load(['classRoom:id,name', 'leader:id,name,photo'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified project
     */
    public function show(int $id)
    {
        try {
            $project = Project::with(['classRoom:id,name', 'leader:id,name,photo'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $project
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }
    }

    /**
     * Update the specified project
     */
    public function update(Request $request, int $id)
    {
        try {
            $project = Project::findOrFail($id);

            $project->update($request->only([
                'title',
                'description',
                'category',
                'status',
                'progress',
                'class_room_id',
                'leader_id',
                'due_date',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $project->load(['classRoom:id,name', 'leader:id,name,photo'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(int $id)
    {
        try {
            $project = Project::findOrFail($id);
            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found'
            ], 404);
        }
    }
}
