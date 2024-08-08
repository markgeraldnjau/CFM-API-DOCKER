<?php

namespace App\Http\Requests\Wagon;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class WagonManufactureRequest extends FormRequest
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
            'name' => ['required', 'string', 'strip_tag', 'max:255', 'unique:wagon_manufactures'],
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
