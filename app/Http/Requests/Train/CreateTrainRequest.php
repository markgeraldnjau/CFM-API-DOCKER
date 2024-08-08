<?php

namespace App\Http\Requests\Train;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class CreateTrainRequest extends FormRequest
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
            'train_name' => 'required|string|max:255',
            'train_number' => 'required|string|unique:trains,train_number',
            'train_type' => 'required|max:100',
            'train_route' => 'required|exists:train_routes,id',
            'reverse_train' => 'nullable|exists:trains,id',
            'startstation' => 'required|exists:train_stations,id',
            'end_station' => 'required|exists:train_stations,id',
            'train_status' => 'required|boolean',
            'train_first_class' => 'nullable|numeric',
            'train_second_class' => 'nullable|numeric',
            'train_third_class' => 'nullable|numeric',
            'travel_hours' => 'required|numeric|min:0',
            'zone_one' => 'required|numeric|in:0,1',
            'zone_two' => 'required|numeric|in:0,2',
            'zone_three' => 'required|numeric|in:0,3',
            'zone_four' => 'required|numeric|in:0,4',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
