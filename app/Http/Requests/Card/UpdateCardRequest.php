<?php

namespace App\Http\Requests\Card;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
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
            'expire_date' => 'required|date',
            'card_type' => 'required|integer',
            'card_status' => 'required|in:A,I,B',
            'card_ownership' => 'required|string',
            'card_company_id' => 'required|integer|exists:card_companies,id',
        ];
    }

    public function messages()
    {
        return [
            'expire_date.required' => 'The expiration date is required.',
            'expire_date.date' => 'The expiration date must be a valid date.',
            'card_type.required' => 'The card type is required.',
            'card_status.required' => 'The card status is required.',
            'card_ownership.required' => 'The card ownership is required.',
            'card_company_id.required' => 'The card company ID is required.',
            'card_company_id.integer' => 'The card company ID must be an integer.',
            'card_company_id.exists' => 'The selected card company ID is invalid.',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
