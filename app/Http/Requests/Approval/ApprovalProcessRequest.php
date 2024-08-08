<?php

namespace App\Http\Requests\Approval;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalProcessRequest extends FormRequest
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
            'file_type' => ['required', 'string', Rule::in([EXCEL, PDF, CSV, TXT])],
            'download_url' => 'required|string|max:255|url',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
