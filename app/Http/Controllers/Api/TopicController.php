<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeleteTopicsRequest;
use App\Http\Requests\TopicRequest;
use App\Http\Requests\TopicSearchRequest;
use App\Http\Resources\Api\TopicResource;
use App\Services\TopicService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    private TopicService $topicService;

    public function __construct(TopicService $topicService)
    {
        $this->topicService = $topicService;
    }

    /**
     * Display a listing of topics
     */
    public function index(Request $request)
    {
        try {
            $fields = ['id', 'subject_id', 'name', 'photo', 'about', 'order'];
            $perPage = $request->integer('per_page', 6);

            // Filter by subject_id (e.g. chapters for a specific subject)
            if ($request->filled('subject_id')) {
                $topics = \App\Models\Topic::where('subject_id', $request->integer('subject_id'))
                    ->with(['lessons', 'subjectExams'])
                    ->orderBy('order', 'asc')
                    ->get();
                return TopicResource::collection($topics);
            }

            // Handle search
            if ($request->filled('search')) {
                $topics = $this->topicService->searchTopics(
                    $request->string('search'),
                    $fields,
                    $perPage
                );
                return TopicResource::collection($topics);
            }

            // Handle all parameter
            if ($request->boolean('all')) {
                $topics = $this->topicService->getAll($fields);
                return TopicResource::collection($topics);
            }

            // Default paginated response
            $topics = $this->topicService->getPaginated($fields, $perPage);
            return TopicResource::collection($topics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve topics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified topic
     */
    public function show(int $id)
    {
        try {
            $topic = $this->topicService->findTopic($id, ['*']);
            return response()->json([
                'success' => true,
                'data' => new TopicResource($topic)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve topic',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created topic
     */
    public function store(TopicRequest $request)
    {
        try {
            $topic = $this->topicService->createTopic($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Topic created successfully',
                'data' => new TopicResource($topic)
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create topic',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified topic
     */
    public function update(TopicRequest $request, int $id)
    {
        try {
            $topic = $this->topicService->updateTopic($id, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Topic updated successfully',
                'data' => new TopicResource($topic)
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update topic',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified topic
     */
    public function destroy(int $id)
    {
        try {
            $this->topicService->deleteTopic($id);
            return response()->json([
                'success' => true,
                'message' => 'Topic deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Topic not found'
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete topic',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search topics with pagination for frontend modal
     */
    public function search(TopicSearchRequest $request)
    {
        try {

            $search = $request->get('q', '') ?? '';
            $page = $request->get('page', 1);
            $perPage = $request->get('limit', 6);
            $fields = ['id', 'name', 'photo'];

            $result = $this->topicService->searchWithPagination($search, $fields, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => TopicResource::collection($result['data']),
                'total' => $result['total'],
                'current_page' => $result['current_page'],
                'per_page' => $result['per_page'],
                'has_more' => $result['has_more']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search topics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
