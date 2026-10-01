<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionOptionBulkStoreRequest extends FormRequest
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
            'options' => 'required|array|min:2|max:10',
            'options.*.name' => 'required|string|max:500',
            'options.*.is_correct' => 'required|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'options.required' => 'Options array is required',
            'options.array' => 'Options must be an array',
            'options.min' => 'At least 2 options are required',
            'options.max' => 'Maximum 10 options are allowed',
            'options.*.name.required' => 'Option name is required',
            'options.*.name.string' => 'Option name must be a valid string',
            'options.*.name.max' => 'Option name must not exceed 500 characters',
            'options.*.is_correct.required' => 'Correct flag is required for each option',
            'options.*.is_correct.boolean' => 'Correct flag must be true or false',
        ];
    }
}