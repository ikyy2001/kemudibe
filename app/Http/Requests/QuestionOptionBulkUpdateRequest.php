<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionOptionBulkUpdateRequest extends FormRequest
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
            'options' => 'required|array|min:1',
            'options.*.id' => 'required|integer|exists:question_options,id',
            'options.*.name' => 'string|max:500',
            'options.*.is_correct' => 'boolean'
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
            'options.min' => 'At least one option is required',
            'options.*.id.required' => 'Option ID is required',
            'options.*.id.integer' => 'Option ID must be an integer',
            'options.*.id.exists' => 'One or more options do not exist',
            'options.*.name.string' => 'Option name must be a valid string',
            'options.*.name.max' => 'Option name must not exceed 500 characters',
            'options.*.is_correct.boolean' => 'Correct flag must be true or false',
        ];
    }
}