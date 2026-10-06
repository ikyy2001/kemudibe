<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255', \App\Rules\TenantRule::unique('subjects', 'name', $id)],
            'code' => 'nullable|string|max:50',
            'passing_grade' => 'nullable|integer|min:0|max:100',
            'tagline' => 'required|string|max:255',
            'about' => 'required|string|max:1000',
            'drive_url' => 'nullable|url|max:1000',
            'status' => 'nullable|string|in:draft,published',
            'photo' => $this->isMethod('post')
                        ? 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
                        : 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'content' => $this->isMethod('post')
                        ? 'nullable|file|mimes:pdf|max:10240'
                        : 'sometimes|file|mimes:pdf|max:10240',
            'topic_id' => ['nullable', 'integer', \App\Rules\TenantRule::exists('topics', 'id')],
            'teacher_id' => [
                'required',
                'integer',
                \App\Rules\TenantRule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if ($value && !User::find($value)?->hasRole('teacher')) {
                        $fail('The selected user must have teacher role.');
                    }
                }
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Subject name is required',
            'tagline.required' => 'Subject tagline is required',
            'about.required' => 'Subject description is required',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be jpeg, png, jpg, or gif format',
            'photo.max' => 'Photo size must not exceed 2MB',
            'content.required' => 'Content PDF file is required',
            'content.file' => 'Content must be a file',
            'content.mimes' => 'Content must be a PDF file',
            'content.max' => 'Content file size must not exceed 10MB',
            'topic_id.required' => 'Topic selection is required',
            'topic_id.exists' => 'Selected topic does not exist',
            'teacher_id.exists' => 'Selected teacher does not exist',
            'teacher_id' => 'Selected user must have teacher role',
        ];
    }
}
