<?php

namespace App\Http\Requests\Cargo;

use App\Exceptions\ValidationException;
use App\Models\Cargo\CargoCustomer;
use App\Models\Cargo\CargoCustomerPayType;
use App\Models\Cargo\CargoCustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

class UpdateCargoCustomerRequest extends FormRequest
{
    private mixed $customer;

    public function __construct(CargoCustomer $customer)
    {
        $this->customer = $customer;
    }

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
            'phone' => ['required', 'string', 'phone_number'],
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
            'tax_number' => ['nullable', 'string', 'max:20', 'strip_tag'],
            'company_reg_number' => [
                'nullable',
                'string',
                'max:20',
                'strip_tag',
                new RequiredIf($this->input('customer_type') == ORGANIZATION_CUSTOMER)
            ],
            'service_type' => ['required', 'string', Rule::in([CARGO_SENDER, CARGO_RECEIVER, CARGO_SENDER_AND_RECEIVER])],
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
