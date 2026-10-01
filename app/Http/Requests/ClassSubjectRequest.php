<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassSubjectRequest extends FormRequest
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
            'class_room_id' => 'required|integer|exists:class_rooms,id',
            'subject_id' => 'required|integer|exists:subjects,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'class_room_id.required' => 'Classroom selection is required',
            'class_room_id.exists' => 'Selected classroom does not exist',
            'subject_id.required' => 'Subject selection is required',
            'subject_id.exists' => 'Selected subject does not exist',
        ];
    }
}