<?php

namespace App\Http\Requests\Auth;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmOTPRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:5'],
            'fb_device_token' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
