<?php

namespace App\Http\Requests\Cargo;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCargoCustomerStatusRequest extends FormRequest
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
            'token' => [
                'required',
                'string',
                'strip_tag',
                Rule::exists('cargo_customers', 'token')
            ],
            'status' => 'required|boolean',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
