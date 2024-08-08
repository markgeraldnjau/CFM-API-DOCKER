<?php

namespace App\Http\Requests\Train;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class TrainLayoutRequest extends FormRequest
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
            'train_id' => 'required|strip_tag|exists:trains,id',
            'wagons' => 'required|array',
            'wagons.*.wagon_id' => [
                'required',
                'exists:wagons,id',
                'distinct',
                'strip_tag'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'wagons.*.wagon_id.required' => 'Each wagon must have an ID.',
            'wagons.*.wagon_id.exists' => 'The selected wagon ID is invalid.',
            'wagons.*.wagon_id.distinct' => 'The same wagon cannot be selected more than once.',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
