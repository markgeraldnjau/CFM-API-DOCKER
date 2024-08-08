<?php

namespace App\Http\Requests\Collection;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;

class GeneralIncidentRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255', 'strip_tag'],
            'description' => ['required', 'string', 'max:500', 'strip_tag'],
            'incident_category_id' => 'strip_tag|exists:incident_categories,id',
            'level' => ['required', 'string', 'strip_tag', function ($attribute, $value, $fail) {
                if (!in_array($value, [PORTAL, KIOSK, APP, POS])) {
                    $fail("The $attribute field must be one of 'Portal', 'Kiosk', 'App', or 'POS'.");
                }
            }],
            'platform' => ['required', 'string', 'strip_tag', function ($attribute, $value, $fail) {
                if (!in_array($value, [PENDING_LOG, RESOLVED_LOG])) {
                    $fail("The $attribute field must be one of 'Pending' or 'Resolved'.");
                }
            }],
        ];
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
