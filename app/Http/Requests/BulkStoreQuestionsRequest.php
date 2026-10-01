<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreQuestionsRequest extends FormRequest
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
            'questions' => 'required|array|min:1',
            'questions.*.subject_exam_id' => 'required|integer|exists:subject_exams,id',
            'questions.*.name' => 'required|string|max:65535',
            'questions.*.timer' => 'nullable|integer|min:0|max:3600',
            'questions.*.type' => 'required|string|in:multiple_choice,essay',
            'questions.*.points' => 'required|integer|min:1|max:100',
            'questions.*.options' => 'array',
            'questions.*.options.*.name' => 'required|string|max:65535',
            'questions.*.options.*.is_correct' => 'required|boolean'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'questions.required' => 'Questions array is required',
            'questions.array' => 'Questions must be an array',
            'questions.min' => 'At least one question is required',
            'questions.*.subject_exam_id.required' => 'Subject exam ID is required for each question',
            'questions.*.subject_exam_id.exists' => 'Selected subject exam does not exist',
            'questions.*.name.required' => 'Question name is required',
            'questions.*.name.max' => 'Question name cannot exceed 1000 characters',
            'questions.*.timer.required' => 'Timer is required for each question',
            'questions.*.timer.min' => 'Timer must be at least 1 second',
            'questions.*.timer.max' => 'Timer cannot exceed 3600 seconds (1 hour)',
            'questions.*.type.required' => 'Question type is required',
            'questions.*.type.in' => 'Question type must be either multiple_choice or essay',
            'questions.*.points.required' => 'Points are required for each question',
            'questions.*.points.min' => 'Points must be at least 1',
            'questions.*.points.max' => 'Points cannot exceed 100',
            'questions.*.options.*.name.required' => 'Option name is required',
            'questions.*.options.*.name.max' => 'Option name cannot exceed 500 characters',
            'questions.*.options.*.is_correct.required' => 'is_correct field is required for each option',
            'questions.*.options.*.is_correct.boolean' => 'is_correct must be true or false'
        ];
    }
}