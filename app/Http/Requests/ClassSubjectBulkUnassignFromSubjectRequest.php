<?php

// UNUSED: ClassSubjectBulkUnassignFromSubjectRequest - Frontend does not use bulk unassign

// namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

// class ClassSubjectBulkUnassignFromSubjectRequest extends FormRequest
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
//             'class_room_ids' => 'required|array|min:1',
//             'class_room_ids.*' => 'integer|exists:class_rooms,id'
//         ];
//     }

//     /**
//      * Get custom messages for validator errors.
//      */
//     public function messages(): array
//     {
//         return [
//             'class_room_ids.required' => 'Classroom IDs array is required',
//             'class_room_ids.array' => 'Classroom IDs must be an array',
//             'class_room_ids.min' => 'At least one classroom ID is required',
//             'class_room_ids.*.integer' => 'Each classroom ID must be an integer',
//             'class_room_ids.*.exists' => 'One or more classrooms do not exist',
//         ];
//     }
// }