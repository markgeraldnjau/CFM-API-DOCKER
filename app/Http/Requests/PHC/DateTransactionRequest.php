<?php

namespace App\Http\Requests\PHC;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Traits\Phc\PhcApiResponse;

class DateTransactionRequest extends FormRequest
{
    use PhcApiResponse;

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
    public function rules()
    {
        return [
            'date' => 'required|date|before_or_equal:today',
        ];
    }

    public function messages()
    {
        return [
            'date.required' => 'The date is required.',
            'date.date' => 'The date must be a valid date.',
            'date.before_or_equal' => 'The date must be today or in the past. Future dates are not allowed.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->first();

        throw new \Illuminate\Validation\ValidationException($validator, $this->error(SUMMARY_DETAILS_API_CODE, $errors, HTTP_UNPROCESSABLE_ENTITY, null));
    }
}
