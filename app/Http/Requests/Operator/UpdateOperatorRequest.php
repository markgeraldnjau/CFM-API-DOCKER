<?php

namespace App\Http\Requests\Operator;

use App\Exceptions\ValidationException;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatorRequest extends FormRequest
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
        $operatorId = $this->route('operator');

        return [
            'full_name' => 'required|string|strip_tag|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operators')->ignore($operatorId),
            ],
            'email' => [
                'nullable',
                'string',
                'email:rfc,dns',
                'max:255',
                Rule::unique('operators')->ignore($operatorId),
            ],
            'phone' => [
                'required',
                'string',
                'max:255',
                'phone_number',
                'strip_tag',
                Rule::unique('operators')->ignore($operatorId),
            ],
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
