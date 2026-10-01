<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassSubjectBulkAssignToClassRoomRequest extends FormRequest
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
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'integer|exists:subjects,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'subject_ids.required' => 'Subject IDs array is required',
            'subject_ids.array' => 'Subject IDs must be an array',
            'subject_ids.min' => 'At least one subject ID is required',
            'subject_ids.*.integer' => 'Each subject ID must be an integer',
            'subject_ids.*.exists' => 'One or more subjects do not exist',
        ];
    }
}