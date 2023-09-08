<?php

namespace App\Http\Requests;

use App\Enums\Constants;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $typeList = [
            Constants::LOGIN_TYPE_BACKOFFICE    => ["class" => "BackOffice",    "switch" => true],
        ];

        $validation_fields = array_merge([
            "login_type" => ['required', 'string', Rule::in($typeList)],
            "credential" => "required|array"
        ], $this->addValidation);
        
        return $validation_fields;
    }
}
