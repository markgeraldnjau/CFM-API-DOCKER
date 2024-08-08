<?php

namespace App\Http\Requests\Cargo;

use App\Exceptions\ValidationException;
use App\Models\Cargo\CargoCustomerPayType;
use App\Models\Cargo\CargoCustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCargoCustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'strip_tag'],
            'address' => ['required', 'string', 'max:255', 'strip_tag'],
            'phone' => ['required', 'string', 'max:255', 'phone_number'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:cargo_customers'],
            'customer_type' => ['required', 'strip_tag', function ($attribute, $value, $fail) {
                if (!CargoCustomerType::where('code', $value)->exists()) {
                    $fail("The $attribute is not a valid cargo customer type.");
                }
            }],
            'customer_pay_type' => ['required', 'strip_tag', function ($attribute, $value, $fail) {
                if (!CargoCustomerPayType::where('code', $value)->exists()) {
                    $fail("The $attribute is not a valid cargo customer pay type.");
                }
            }],
            'tax_number' => ['nullable', 'string', 'max:20', 'strip_tag'], // Allow null and validate as string with max length 20
            'company_reg_number' => ['nullable', 'string', 'max:20', 'required_if:customer_type,11', 'unique:cargo_customers', 'strip_tag'],
            'service_type' => ['required', 'string', Rule::in([CARGO_SENDER, CARGO_RECEIVER, CARGO_SENDER_AND_RECEIVER])], // Validate as one of 'S', 'R', or 'B'
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
