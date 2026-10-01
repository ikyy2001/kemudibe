<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionAnswerGradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'points_earned' => 'required|integer|min:0|max:100',
            'has_passed' => 'sometimes|boolean',
            'feedback' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'points_earned.required' => 'Points earned is required',
            'points_earned.integer' => 'Points earned must be an integer',
            'points_earned.min' => 'Points earned must be at least 0',
            'points_earned.max' => 'Points earned must not exceed 100',
            'has_passed.required' => 'Pass status is required',
            'has_passed.boolean' => 'Pass status must be true or false',
            'feedback.string' => 'Feedback must be a valid string',
            'feedback.max' => 'Feedback must not exceed 1000 characters',
        ];
    }
}