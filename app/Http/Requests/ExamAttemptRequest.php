<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExamAttemptRequest extends FormRequest
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
            'student_id' => 'required|integer|exists:users,id',
            'subject_exam_id' => 'required|integer|exists:subject_exams,id',
            'is_completed' => 'sometimes|boolean',
            'total_questions' => 'required|integer|min:1',
            'answered_questions' => 'required|integer|min:0',
            'total_points' => 'sometimes|integer|min:0',
            'points_earned' => 'sometimes|integer|min:0',
            'has_passed' => 'sometimes|boolean',
            'completed_at' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student identification is required',
            'student_id.exists' => 'Selected student does not exist',
            'subject_exam_id.required' => 'Exam selection is required',
            'subject_exam_id.exists' => 'Selected exam does not exist',
            'is_completed.boolean' => 'Completion status must be true or false',
            'total_questions.required' => 'Total questions count is required',
            'total_questions.integer' => 'Total questions must be a number',
            'total_questions.min' => 'Total questions must be at least 1',
            'answered_questions.required' => 'Answered questions count is required',
            'answered_questions.integer' => 'Answered questions must be a number',
            'answered_questions.min' => 'Answered questions cannot be negative',
            'total_points.integer' => 'Total points must be a number',
            'total_points.min' => 'Total points cannot be negative',
            'points_earned.integer' => 'Points earned must be a number',
            'points_earned.min' => 'Points earned cannot be negative',
            'has_passed.boolean' => 'Pass status must be true or false',
            'completed_at.date' => 'Completion date must be a valid date',
        ];
    }
}