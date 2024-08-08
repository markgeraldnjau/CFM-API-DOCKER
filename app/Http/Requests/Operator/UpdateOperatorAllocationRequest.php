<?php

namespace App\Http\Requests\Operator;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatorAllocationRequest extends FormRequest
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
            'operator_id' => 'required|exists:operators,id',
            'train_id' => 'required|exists:trains,id',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
