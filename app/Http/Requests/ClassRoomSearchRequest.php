<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassRoomSearchRequest extends FormRequest
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
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.string' => 'Search query must be a string',
            'q.max' => 'Search query cannot exceed 255 characters',
            'page.integer' => 'Page must be an integer',
            'page.min' => 'Page must be at least 1',
            'limit.integer' => 'Limit must be an integer',
            'limit.min' => 'Limit must be at least 1',
            'limit.max' => 'Limit cannot exceed 50'
        ];
    }

    /**
     * Get the search query with default value
     */
    public function getSearchQuery(): string
    {
        return $this->input('q', '');
    }

    /**
     * Get the page number with default value
     */
    public function getPage(): int
    {
        return $this->input('page', 1);
    }

    /**
     * Get the limit with default value
     */
    public function getLimit(int $default = 6): int
    {
        return $this->input('limit', $default);
    }
}