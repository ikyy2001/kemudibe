<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassRoomRequest extends FormRequest
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
        $id = $this->route('class_room');

        return [
            'name' => 'required|string|max:255|unique:class_rooms,name,' . $id,
            'photo' => $this->isMethod('post')
                        ? 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
                        : 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'grade' => 'required|integer|min:1|max:12',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Classroom name is required',
            'name.unique' => 'Classroom name already exists',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be jpeg, png, jpg, or gif format',
            'photo.max' => 'Photo size must not exceed 2MB',
            'grade.required' => 'Grade level is required',
            'grade.min' => 'Grade level must be at least 1',
            'grade.max' => 'Grade level must not exceed 12',
        ];
    }
}