<?php

namespace App\Http\Requests\Train;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleRequest extends FormRequest
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
            'day_of_the_week' => ['required', 'numeric', Rule::in([MONDAY, TUESDAY, WEDNESDAY, THURSDAY, FRIDAY, SATURDAY, SUNDAY, ALL_DAYS])],
            'trip_duration_hours' => ['required', 'numeric', Rule::in([DURATION_24_HOURS, DURATION_48_HOURS])],
            'departure_time' => 'required|date_format:H:i',
            'est_destination_time' => [
                'required',
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    $departureTime = $this->input('departure_time');
                    $tripDuration = $this->input('trip_duration_hours');

                    if ($tripDuration == DURATION_24_HOURS && strtotime($value) <= strtotime($departureTime)) {
                        $fail('The estimated destination time must be after the departure time for a 24-hour trip.');
                    }
                }
            ],
            'train_layout_id' => 'required|strip_tag|exists:train_layouts,id',
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
