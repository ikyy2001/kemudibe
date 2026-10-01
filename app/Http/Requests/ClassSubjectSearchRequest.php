<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassSubjectSearchRequest extends FormRequest
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
            'q' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'q.string' => 'Search query must be a valid string',
            'q.max' => 'Search query must not exceed 255 characters',
            'page.integer' => 'Page must be a valid integer',
            'page.min' => 'Page must be at least 1',
            'limit.integer' => 'Limit must be a valid integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit must not exceed 50',
        ];
    }
}