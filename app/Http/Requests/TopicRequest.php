<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopicRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'subject_id' => ['nullable', 'integer', \App\Rules\TenantRule::exists('subjects', 'id')],
            'about' => 'nullable|string|max:1000',
            'order' => 'nullable|integer',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Topic name is required',
            'name.unique' => 'Topic name already exists',
            'about.required' => 'Topic description is required',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be jpeg, png, jpg, or gif format',
            'photo.max' => 'Photo size must not exceed 2MB',
        ];
    }
}