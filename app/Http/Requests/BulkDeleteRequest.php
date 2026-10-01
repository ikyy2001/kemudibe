<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
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
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer'
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'IDs array is required',
            'ids.array' => 'IDs must be an array',
            'ids.min' => 'At least one ID is required',
            'ids.*.integer' => 'Each ID must be an integer'
        ];
    }

    /**
     * Configure the validator instance for additional rules based on context
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Get the table name from route or determine context
            $routeName = $this->route()->getName();
            $table = $this->getTableFromRoute($routeName);
            
            if ($table) {
                // Add exists validation for the specific table
                $this->addExistsRule($validator, $table);
            }
        });
    }

    /**
     * Determine table name from route
     */
    private function getTableFromRoute(string $routeName): ?string
    {
        $tableMap = [
            'exam-questions.bulk-destroy' => 'exam_questions',
            'classrooms.bulk-destroy' => 'class_rooms',
            'teachers.bulk-destroy' => 'users',
            'students.bulk-destroy' => 'users',
            'subjects.bulk-destroy' => 'subjects',
            'topics.bulk-destroy' => 'topics'
        ];

        return $tableMap[$routeName] ?? null;
    }

    /**
     * Add exists validation rule for specific table
     */
    private function addExistsRule($validator, string $table)
    {
        $ids = $this->input('ids', []);
        
        foreach ($ids as $index => $id) {
            $validator->addRules([
                "ids.{$index}" => "exists:{$table},id"
            ]);
        }
    }
}