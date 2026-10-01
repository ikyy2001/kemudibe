<?php

// UNUSED: ExamAttemptBulkActionRequest - Frontend does not use bulk actions on exam attempts

// namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

// class ExamAttemptBulkActionRequest extends FormRequest
// {
//     /**
//      * Determine if the user is authorized to make this request.
//      */
//     public function authorize(): bool
//     {
//         return true;
//     }

//     /**
//      * Get the validation rules that apply to the request.
//      *
//      * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
//      */
//     public function rules(): array
//     {
//         return [
//             'action' => 'required|string|in:delete,complete,reset',
//             'attempt_ids' => 'required|array|min:1',
//             'attempt_ids.*' => 'integer|exists:exam_attempts,id'
//         ];
//     }

//     /**
//      * Get custom messages for validator errors.
//      */
//     public function messages(): array
//     {
//         return [
//             'action.required' => 'Action is required',
//             'action.string' => 'Action must be a valid string',
//             'action.in' => 'Action must be one of: delete, complete, reset',
//             'attempt_ids.required' => 'Attempt IDs array is required',
//             'attempt_ids.array' => 'Attempt IDs must be an array',
//             'attempt_ids.min' => 'At least one attempt ID is required',
//             'attempt_ids.*.integer' => 'Each attempt ID must be an integer',
//             'attempt_ids.*.exists' => 'One or more exam attempts do not exist',
//         ];
//     }
// }