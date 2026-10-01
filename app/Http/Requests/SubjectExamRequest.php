<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubjectExamRequest extends FormRequest
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
        $id = $this->route('subject_exam');

        return [
            'subject_id' => 'required|integer|exists:subjects,id',
            'name' => [
                'required',
                'string',
                'max:255',
                // Check for unique name within the same subject, excluding current record when updating
                \Illuminate\Validation\Rule::unique('subject_exams', 'name')->where(function ($query) {
                    return $query->where('subject_id', $this->subject_id);
                })->ignore($id)
            ],
            'about' => 'required|string|max:1000',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
            'passing_grade' => 'nullable|integer|min:0|max:100',
            'category' => 'nullable|string|in:daily_quiz,mid_exam,final_exam,tryout',
            'topic_id' => 'nullable|integer|exists:topics,id',
            'token' => 'nullable|string|max:50',
            'token_expires_at' => 'nullable|date',
            'started_at' => 'required|date|after_or_equal:' . today()->toDateString(),
            'ended_at' => 'required|date|after:started_at',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Subject selection is required',
            'subject_id.exists' => 'Selected subject does not exist',
            'name.required' => 'Exam name is required',
            'name.max' => 'Exam name must not exceed 255 characters',
            'name.unique' => 'An exam with this name already exists for this subject. Please choose a different name.',
            'about.required' => 'Exam description is required',
            'about.max' => 'Exam description must not exceed 1000 characters',
            'started_at.required' => 'Start date is required',
            'started_at.date' => 'Start date must be a valid date',
            'started_at.after_or_equal' => 'Start date must be today or later',
            'ended_at.required' => 'End date is required',
            'ended_at.date' => 'End date must be a valid date',
            'ended_at.after' => 'End date must be after start date',
        ];
    }
}