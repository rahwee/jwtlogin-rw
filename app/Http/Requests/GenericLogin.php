<?php

namespace App\Http\Requests;

use App\Enums\Constants;
use Illuminate\Validation\Rule;

use App\Services\Jwt\JWTManager;
use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class GenericLogin extends BaseRequest
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
       
        $APP_TYPES = (new JWTManager())->getListTypeApp();

        $validation_fields = array_merge([
            "login_type" => ['required', 'string', Rule::in($APP_TYPES)],
            "credential" => "required|array"
        ], $this->addValidation);
        
        return $validation_fields;
    
    }

    protected function prepareForValidation()
    {
        $login       = $this->get('login_type');
        $authManager = new JWTManager(strtoupper($login));
        $authService = $authManager->getService();

        $this->addValidation = $authService && method_exists($authService, 'getRules') ? $authService->getRules() : [];
    }

    public function bodyParameters()
    {
        return array(
            'login_type' => getDescriptionParam('The type to login', Constants::LOGIN_TYPE_BACKOFFICE),
        );
    }
}

