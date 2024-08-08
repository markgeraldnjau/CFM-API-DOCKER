<?php

namespace App\Http\Requests\Operator;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class OperatorRequest extends FormRequest
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
            'full_name' => 'required|string|strip_tag|max:255',
            'username' => 'required|string|strip_tag|max:255|unique:operators',
            'email' => 'nullable|string|email|max:255|unique:operators',
            'phone' => 'required|string|phone_number|max:15',
            'train_line_id' => 'required|integer|exists:train_lines,id',
            'operator_type_code' => 'required|integer|exists:operator_types,id',
            'station_id' => 'required|integer|exists:train_stations,id',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
