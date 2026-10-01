<?php

// UNUSED: BulkDeleteSubjectsRequest - Frontend does not use bulk delete for subjects

// namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

// class BulkDeleteSubjectsRequest extends FormRequest
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
//             'ids' => 'required|array|min:1',
//             'ids.*' => 'integer|exists:subjects,id'
//         ];
//     }

//     /**
//      * Get custom error messages for validation rules.
//      *
//      * @return array<string, string>
//      */
//     public function messages(): array
//     {
//         return [
//             'ids.required' => 'IDs array is required',
//             'ids.array' => 'IDs must be an array',
//             'ids.min' => 'At least one ID is required',
//             'ids.*.integer' => 'Each ID must be an integer',
//             'ids.*.exists' => 'One or more subjects do not exist'
//         ];
//     }
// }