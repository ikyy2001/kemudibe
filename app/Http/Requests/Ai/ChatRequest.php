<?php

namespace App\Http\Requests\Ai;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation (trim message).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('message') && is_string($this->message)) {
            $this->merge([
                'message' => trim($this->message),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxChars = (int) config('ai.max_input_chars', 300);

        return [
            'message' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($maxChars) {
                    $trimmed = trim((string) $value);
                    $len = mb_strlen($trimmed);

                    if ($len < 2) {
                        $fail('Pertanyaan minimal 2 karakter.');
                        return;
                    }

                    if ($len > $maxChars) {
                        $fail("Pertanyaan maksimal {$maxChars} karakter.");
                        return;
                    }

                    // Tolak pesan yang hanya berisi karakter berulang tanpa makna (mis. aaaaaa, ....., ????)
                    if (preg_match('/^(.)\1{4,}$/u', $trimmed)) {
                        $fail('Pertanyaan tidak valid atau mengandung karakter berulang.');
                        return;
                    }

                    // Tolak jika sama sekali tidak mengandung karakter alfanumerik (hanya simbol/tanda baca)
                    if (!preg_match('/[\p{L}\p{N}]/u', $trimmed)) {
                        $fail('Pertanyaan harus mengandung teks yang bermakna.');
                        return;
                    }
                },
            ],
            'history' => ['nullable', 'array', 'max:6'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:600'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     */
    public function messages(): array
    {
        $maxChars = (int) config('ai.max_input_chars', 300);

        return [
            'message.required' => 'Pesan pertanyaan wajib diisi.',
            'message.string' => 'Pesan pertanyaan harus berupa teks.',
            'history.array' => 'Format riwayat percakapan tidak valid.',
            'history.max' => 'Riwayat percakapan maksimal 6 pesan.',
            'history.*.role.required' => 'Role riwayat pesan wajib diisi.',
            'history.*.role.in' => 'Role riwayat pesan hanya boleh "user" atau "assistant".',
            'history.*.content.required' => 'Konten riwayat pesan wajib diisi.',
            'history.*.content.string' => 'Konten riwayat pesan harus berupa teks.',
            'history.*.content.max' => 'Konten tiap riwayat pesan maksimal 600 karakter.',
        ];
    }

    /**
     * Handle a failed validation attempt with standard project JSON response.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
