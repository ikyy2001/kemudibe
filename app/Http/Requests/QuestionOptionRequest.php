<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionOptionRequest extends FormRequest
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
            'is_correct' => 'required|boolean',
            'name' => 'required|string|max:500',
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
            'is_correct.required' => 'Correct answer status is required',
            'is_correct.boolean' => 'Correct answer status must be true or false',
            'name.required' => 'Option text is required',
            'name.max' => 'Option text must not exceed 500 characters',
        ];
    }
}