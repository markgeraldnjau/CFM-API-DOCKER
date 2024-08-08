<?php

namespace App\Http\Requests\Wagon;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class WagonRequest extends FormRequest
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
            'model' => 'required|strip_tag|unique:wagons',
            'serial_number' => 'required|strip_tag|unique:wagons',
            'wagon_type_id' => 'strip_tag|exists:train_wagon_types,id',
            'layout_id' => 'strip_tag|exists:wagon_layouts,id',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
