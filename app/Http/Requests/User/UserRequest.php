<?php

namespace App\Http\Requests\User;

use App\Exceptions\ValidationException;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $rules = [
            'first_name' => 'required|string|strip_tag|max:255',
            'last_name' => 'required|string|strip_tag|max:255',
            'username' => 'required|string|strip_tag|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|strip_tag|max:15|unique:users',
            'role_id' => 'required|integer|exists:roles,id',
        ];

        $role = Role::find($this->input('role_id'), ['code']);

        if ($role->code == OPERADOR) {
            $rules['train_line_id'] = 'required|integer|exists:train_lines,id';
            $rules['operator_type_code'] = 'required|integer|exists:operator_types,id';
            $rules['station_id'] = 'required|integer|exists:train_stations,id';
            $rules['username'] = 'required|string|strip_tag|max:255|unique:operators';
            $rules['email'] = 'required|email|string|max:255|unique:operators';
            $rules['phone_number'] = 'phone_number|unique:operators,phone';
        }

        return $rules;
    }

    protected function failedValidation($validator)
    {
        throw new ValidationException($validator);
    }
}
