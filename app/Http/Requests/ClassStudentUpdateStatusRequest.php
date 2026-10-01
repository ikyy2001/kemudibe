<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassStudentUpdateStatusRequest extends FormRequest
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
            'has_passed' => 'required|boolean',
            'rapport' => 'nullable|string|max:1000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'has_passed.required' => 'Pass status is required',
            'has_passed.boolean' => 'Pass status must be true or false',
            'rapport.string' => 'Rapport must be a valid string',
            'rapport.max' => 'Rapport must not exceed 1000 characters',
        ];
    }
}