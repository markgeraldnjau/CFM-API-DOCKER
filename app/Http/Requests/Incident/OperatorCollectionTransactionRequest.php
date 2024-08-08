<?php

namespace App\Http\Requests\Incident;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class OperatorCollectionTransactionRequest extends FormRequest
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
            'transaction_type' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!in_array($value, [TRAIN_CASH_PAYMENT, TOP_UP_CARD, CARGO])) {
                    $fail("The $attribute must be one of 'Cash', 'Top Up', or 'Cargo' transactions.");
                }
            }],
            'asc_train_number' => 'exists:trains,train_number',
            'desc_train_number' => 'exists:trains,train_number',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
