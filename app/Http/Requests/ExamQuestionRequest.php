<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\SubjectExam;

class ExamQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handle authorization in middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_exam_id' => ['required', 'integer', \App\Rules\TenantRule::exists('subject_exams', 'id')],
            'name' => 'required|string|max:65535',
            'timer' => 'nullable|integer|min:0|max:3600',
            'type' => 'required|string|in:multiple_choice,essay',
            'points' => [
                'required',
                'integer',
                'min:1',
                'max:100',
                function ($attribute, $value, $fail) {
                    $this->validateTotalPointsLimit($attribute, $value, $fail);
                }
            ],
        ];
    }

    /**
     * Validate that adding this question won't exceed the 100-point exam limit
     */
    protected function validateTotalPointsLimit($attribute, $value, $fail)
    {
        $subjectExamId = $this->input('subject_exam_id');
        
        if (!$subjectExamId) {
            return; // Let the exists validation handle this
        }

        // Get current exam with questions
        $exam = SubjectExam::with('examQuestions')->find($subjectExamId);
        
        if (!$exam) {
            return; // Let the exists validation handle this
        }

        // Calculate current total points
        $currentPoints = $exam->examQuestions->sum('points');
        
        // For updates, subtract the current question's points
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $questionId = $this->route('id') ?? $this->route('exam_question');
            if ($questionId) {
                $currentQuestion = $exam->examQuestions->where('id', $questionId)->first();
                if ($currentQuestion) {
                    $currentPoints -= $currentQuestion->points;
                }
            }
        }

        // Check if adding new points would exceed limit
        $newTotal = $currentPoints + $value;
        
        if ($newTotal > 100) {
            $availablePoints = 100 - $currentPoints;
            $fail("Adding {$value} points would exceed the 100-point exam limit. Only {$availablePoints} points available.");
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subject_exam_id.required' => 'Exam selection is required',
            'subject_exam_id.exists' => 'Selected exam does not exist',
            'name.required' => 'Question text is required',
            'name.max' => 'Question text must not exceed 1000 characters',
            'timer.required' => 'Timer duration is required',
            'timer.integer' => 'Timer must be a number',
            'timer.min' => 'Timer must be at least 1 second',
            'timer.max' => 'Timer must not exceed 3600 seconds (1 hour)',
            'type.required' => 'Question type is required',
            'type.in' => 'Question type must be either multiple_choice or essay',
            'points.required' => 'Points value is required',
            'points.integer' => 'Points must be a number',
            'points.min' => 'Points must be at least 1',
            'points.max' => 'Points must not exceed 100',
        ];
    }
}