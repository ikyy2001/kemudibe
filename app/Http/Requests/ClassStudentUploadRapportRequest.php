<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClassStudentUploadRapportRequest extends FormRequest
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
            'rapport' => 'required|file|mimes:pdf|max:10240'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'rapport.required' => 'Rapport PDF file is required',
            'rapport.file' => 'Rapport must be a file',
            'rapport.mimes' => 'Rapport must be a PDF file',
            'rapport.max' => 'Rapport file size must not exceed 10MB',
        ];
    }
}