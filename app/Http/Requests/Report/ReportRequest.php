<?php

namespace App\Http\Requests\Report;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
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
            'report_code' => 'required|strip_tag|exists:reports,code',
            'file_type' => [
                'required',
                'string',
                Rule::in([EXCEL, PDF, CSV, TXT])
            ],
            'parameters.*.start_date' => 'nullable|date',
            'parameters.*.end_date' => 'nullable|date|after_or_equal:parameters.*.start_date',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'report_code.required' => 'The report code is required.',
            'report_code.exists' => 'The selected report code is invalid.',
            'report_name.required' => 'The report name is required.',
            'report_name.string' => 'The report name must be a string.',
            'report_name.max' => 'The report name must not exceed 255 characters.',
            'from_date.required' => 'The start date is required.',
            'from_date.date' => 'The start date must be a valid date.',
            'to_date.required' => 'The end date is required.',
            'to_date.date' => 'The end date must be a valid date.',
            'to_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'created_by.required' => 'The created by field is required.',
            'created_by.exists' => 'The selected created by is invalid.',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
