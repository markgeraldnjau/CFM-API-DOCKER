<?php

namespace App\Http\Requests\Cargo;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class CargoPriceRequest extends FormRequest
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
            'category_id' => ['required', 'string', 'max:255', 'exists:cargo_categories,id'],
            'new_cargo_price_file' => ['required', 'file', 'mimes:xls,xlsx', 'max:3072']
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
