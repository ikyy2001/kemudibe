<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionOptionReorderRequest extends FormRequest
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
            'order' => 'required|array|min:1',
            'order.*.id' => 'required|integer|exists:question_options,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order.required' => 'Order array is required',
            'order.array' => 'Order must be an array',
            'order.min' => 'At least one option is required',
            'order.*.id.required' => 'Option ID is required',
            'order.*.id.integer' => 'Option ID must be an integer',
            'order.*.id.exists' => 'One or more options do not exist',
        ];
    }
}