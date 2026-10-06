<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'institution_code' => 'required|string|max:60',
            'username'         => 'required|string|max:60',
            'password'         => 'required|string',
            'remember'         => 'nullable|boolean',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'institution_code.required' => 'Kode lembaga wajib diisi.',
            'username.required'         => 'ID Pengguna / Username wajib diisi.',
            'password.required'         => 'Password wajib diisi.',
        ];
    }
}
