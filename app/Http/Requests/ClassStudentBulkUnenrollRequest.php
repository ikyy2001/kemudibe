<?php

// UNUSED: ClassStudentBulkUnenrollRequest - Frontend does not use bulk unenroll

// namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

// class ClassStudentBulkUnenrollRequest extends FormRequest
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
//             'student_ids' => 'required|array|min:1',
//             'student_ids.*' => 'integer|exists:users,id'
//         ];
//     }

//     /**
//      * Get custom messages for validator errors.
//      */
//     public function messages(): array
//     {
//         return [
//             'student_ids.required' => 'Student IDs array is required',
//             'student_ids.array' => 'Student IDs must be an array',
//             'student_ids.min' => 'At least one student ID is required',
//             'student_ids.*.integer' => 'Each student ID must be an integer',
//             'student_ids.*.exists' => 'One or more students do not exist',
//         ];
//     }
// }