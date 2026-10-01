<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassStudentRequest extends FormRequest
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
        $id = $this->route('class_student');

        return [
            'student_id' => 'required|integer|exists:users,id',
            'class_room_id' => 'required|integer|exists:class_rooms,id',
            'has_passed' => 'sometimes|boolean',
            'rapport' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Student selection is required',
            'student_id.exists' => 'Selected student does not exist',
            'class_room_id.required' => 'Classroom selection is required',
            'class_room_id.exists' => 'Selected classroom does not exist',
            'has_passed.boolean' => 'Pass status must be true or false',
            'rapport.max' => 'Rapport must not exceed 1000 characters',
        ];
    }
}