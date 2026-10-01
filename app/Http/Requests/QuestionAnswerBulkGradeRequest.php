<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionAnswerBulkGradeRequest extends FormRequest
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
            'answers' => 'required|array|min:1',
            'answers.*.id' => 'required|integer|exists:question_answers,id',
            'answers.*.points_earned' => 'required|integer|min:0|max:100',
            'answers.*.has_passed' => 'required|boolean',
            'answers.*.feedback' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Answers array is required',
            'answers.array' => 'Answers must be an array',
            'answers.min' => 'At least one answer is required',
            'answers.*.id.required' => 'Answer ID is required',
            'answers.*.id.integer' => 'Answer ID must be an integer',
            'answers.*.id.exists' => 'One or more answers do not exist',
            'answers.*.points_earned.required' => 'Points earned is required for each answer',
            'answers.*.points_earned.integer' => 'Points earned must be an integer',
            'answers.*.points_earned.min' => 'Points earned must be at least 0',
            'answers.*.points_earned.max' => 'Points earned must not exceed 100',
            'answers.*.has_passed.required' => 'Pass status is required for each answer',
            'answers.*.has_passed.boolean' => 'Pass status must be true or false',
            'answers.*.feedback.string' => 'Feedback must be a valid string',
            'answers.*.feedback.max' => 'Feedback must not exceed 1000 characters',
        ];
    }
}