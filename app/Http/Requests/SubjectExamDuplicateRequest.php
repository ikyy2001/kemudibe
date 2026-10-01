<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubjectExamDuplicateRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'started_at' => 'required|date|after_or_equal:today',
            'ended_at' => 'required|date|after:started_at',
            'subject_id' => 'sometimes|integer|exists:subjects,id',
            'about' => 'sometimes|string|max:1000',
            'copy_questions' => 'sometimes|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Exam name is required',
            'name.string' => 'Exam name must be a valid string',
            'name.max' => 'Exam name must not exceed 255 characters',
            'started_at.required' => 'Start date is required',
            'started_at.date' => 'Start date must be a valid date',
            'started_at.after_or_equal' => 'Start date must be today or later',
            'ended_at.required' => 'End date is required',
            'ended_at.date' => 'End date must be a valid date',
            'ended_at.after' => 'End date must be after start date',
            'subject_id.integer' => 'Subject ID must be an integer',
            'subject_id.exists' => 'Selected subject does not exist',
            'about.string' => 'Description must be a valid string',
            'about.max' => 'Description must not exceed 1000 characters',
            'copy_questions.boolean' => 'Copy questions flag must be true or false',
        ];
    }
}