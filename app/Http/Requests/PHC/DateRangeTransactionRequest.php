<?php

namespace App\Http\Requests\PHC;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Traits\Phc\PhcApiResponse;

class DateRangeTransactionRequest extends FormRequest
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
            'from_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:from_date',
        ];
    }

    public function messages()
    {
        return [
            'from_date.required' => 'The from_date is required.',
            'from_date.date' => 'The from_date must be a valid date.',
            'from_date.before_or_equal' => 'The from_date must be before or equal to the end_date.',
            'end_date.required' => 'The end_date is required.',
            'end_date.date' => 'The end_date must be a valid date.',
            'end_date.after_or_equal' => 'The end_date must be after or equal to the from_date.',
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


        throw new \Illuminate\Validation\ValidationException($validator, $this->error($this->getRequestID(), $errors, HTTP_UNPROCESSABLE_ENTITY, null));
    }

    protected function getRequestID()
    {
        $endpoint = $this->path(); // Get the current endpoint path

        // Set the API code based on the endpoint
        if ($endpoint === 'api/phc/ticket_transactions') {
            return  TICKET_TRANSACTION_API_CODE;
        } elseif ($endpoint === 'api/phc/cargo_transactions') {
            return  CARGO_TRANSACTION_API_CODE;
        }
    }
}
