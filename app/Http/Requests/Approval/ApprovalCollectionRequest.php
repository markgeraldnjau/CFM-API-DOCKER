<?php

namespace App\Http\Requests\Approval;

use App\Exceptions\ValidationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalCollectionRequest extends FormRequest
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
    public function rules()
    {
        // Define the base validation rules
        $rules = [
            'received_amount' => ['required', 'numeric', 'max:999999.99', 'min:0'],
            'asc_physical_amount' => ['required', 'numeric', 'max:999999.99', 'min:0'],
            'asc_manual_amount' => ['nullable', 'numeric', 'max:999999.99', 'min:0'],
            'desc_physical_amount' => ['required', 'numeric', 'max:999999.99', 'min:0'],
            'desc_manual_amount' => ['nullable', 'numeric', 'max:999999.99', 'min:0'],
            'comment' => ['nullable', 'string', 'max:255', 'strip_tag'],
            'approval_process_id' => ['required', 'numeric', 'exists:approval_processes,id'],
        ];

        if ($this->input('any_asc_data')) {
            $rules['asc_manual_amount'] = ['required', 'numeric', 'max:999999.99'];
        }

        if ($this->input('any_desc_data')) {
            $rules['desc_manual_amount'] = ['required', 'numeric', 'max:999999.99'];
        }

        // Add custom validation rule for summation check
        $rules['received_amount'][] = function($attribute, $value, $fail) {
            $ascPhysicalAmount = $this->input('asc_physical_amount');
            $descPhysicalAmount = $this->input('desc_physical_amount');
            $receivedAmount = $this->input('received_amount');

            if (is_numeric($ascPhysicalAmount) && is_numeric($descPhysicalAmount) && is_numeric($receivedAmount)) {
                $totalPhysicalAmount = (float)$ascPhysicalAmount + (float)$descPhysicalAmount;

                if ($totalPhysicalAmount !== (float)$receivedAmount) {
                    $fail('The sum of ascending physical amount and descending physical amount must equal total received amount.');
                }
            }
        };

        return $rules;
    }


    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
