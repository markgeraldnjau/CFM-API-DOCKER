<?php

namespace App\Http\Requests\Wagon;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class WagonLayoutRequest extends FormRequest
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
            'name' => 'required|strip_tag|unique:wagon_layouts',
            'label' => 'required|strip_tag|unique:wagon_layouts',
            'class_id' => 'strip_tag|exists:train_wagon_class,id',
            'manufacture_id' => 'strip_tag|exists:wagon_manufactures,id',
            'seat_type' => 'strip_tag|exists:seat_types,code',
            'normal_seats' => 'strip_tag|required|integer|min:1',
            'seat_rows' => 'strip_tag|required|integer|min:1',
            'seat_columns' => [
                'required',
                'strip_tag',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $totalSeats = $this->input('seat_rows') * $value;
                    if ($totalSeats < $this->input('normal_seats')) {
                        $fail("The total number of seats ($totalSeats) must be greater than or equal to the total number of normal seats.");
                    }
                },
            ],
            'aisle_interval' => 'strip_tag|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'manufacture_id.unique' => 'A record with the same combination of manufacture, class, and seat_type already exists.'
        ];
    }


    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
