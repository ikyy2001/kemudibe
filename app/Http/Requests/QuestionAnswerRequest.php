<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionAnswerRequest extends FormRequest
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
            'exam_question_id' => 'required|integer|exists:exam_questions,id',
            'student_id' => 'required|integer|exists:users,id',
            'answer_text' => 'required|string|max:2000',
            'has_passed' => 'sometimes|boolean',
            'points_earned' => 'nullable|integer|min:0|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'exam_question_id.required' => 'Question selection is required',
            'exam_question_id.exists' => 'Selected question does not exist',
            'student_id.required' => 'Student identification is required',
            'student_id.exists' => 'Selected student does not exist',
            'answer_text.required' => 'Answer is required',
            'answer_text.max' => 'Answer must not exceed 2000 characters',
            'has_passed.boolean' => 'Pass status must be true or false',
            'points_earned.integer' => 'Points earned must be a number',
            'points_earned.min' => 'Points earned cannot be negative',
            'points_earned.max' => 'Points earned cannot exceed 100',
        ];
    }
}