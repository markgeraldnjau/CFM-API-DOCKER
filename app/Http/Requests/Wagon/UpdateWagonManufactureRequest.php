<?php

namespace App\Http\Requests\Wagon;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWagonManufactureRequest extends FormRequest
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
        $manufacture = $this->route('wagon_manufacture');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('wagon_manufactures', 'name')->ignore($manufacture, 'token'),
            ],
        ];
    }
}
