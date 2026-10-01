<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $id = $this->route('user');

        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($id)
            ],
            'password' => $this->isMethod('post') 
                        ? 'required|string|min:8|confirmed'
                        : 'sometimes|string|min:8|confirmed',
            'photo' => $this->isMethod('post')
                        ? 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
                        : 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'gender' => 'required|string|in:male,female',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.max' => 'Name must not exceed 255 characters',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'photo.image' => 'Photo must be an image file',
            'photo.mimes' => 'Photo must be jpeg, png, jpg, or gif format',
            'photo.max' => 'Photo size must not exceed 2MB',
            'gender.required' => 'Gender selection is required',
            'gender.in' => 'Gender must be either male or female',
        ];
    }
}