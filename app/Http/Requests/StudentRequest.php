<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Handle authorization in middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('student');

        $rules = [
            'name' => 'required|string|max:255',
            'username' => ['nullable', 'string', 'max:60', \App\Rules\TenantRule::unique('users', 'username', $id)],
            'email' => ['required', 'email', 'max:255', \App\Rules\TenantRule::unique('users', 'email', $id)],
            'gender' => 'required|in:male,female',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // Quota check & password required for new student
        if ($this->isMethod('post')) {
            $context = app(\App\Services\InstitutionContext::class);
            $institution = $context->get();
            if ($institution && !$institution->canAddStudents(1)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quota' => ["Batas kuota siswa untuk lembaga ini telah tercapai ({$institution->max_students} siswa). Hubungi Super Admin untuk menambah kuota."]
                ]);
            }
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Student name is required',
            'name.max' => 'Student name must not exceed 255 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please enter a valid email address',
            'email.unique' => 'This email address is already taken',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be either male or female',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'photo.required' => 'Student photo is required',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be jpeg, png, jpg, or gif format',
            'photo.max' => 'Photo size must not exceed 2MB',
        ];
    }
}