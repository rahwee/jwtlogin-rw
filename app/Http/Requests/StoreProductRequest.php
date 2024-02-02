<?php

namespace App\Http\Requests;

use App\Enums\Constants;
use App\Services\SVProduct;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends BaseRequest
{

    public function getService()
    {
        return new SVProduct;
    }
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
        $data = $this->getService()->getRules();
        return $data;
    }

    public function bodyParameters()
    {
        $data = array(
            'name'             => getDescriptionParam(Constants::DES_PARAM_NAME_LOCALIZE, 'Coca Cola'),
            'image_url'        => getDescriptionParam('The image path for after upload'),
            'dish_type'        => getDescriptionParam('The product type', 'DISH'),
            'quantity'         => getDescriptionParam('The quantity product', 10),
            'dish_code'        => getDescriptionParam('The product code', 'XCEXDS'),
            'active'           => getDescriptionParam('The active product', 1),
            'description'      => getDescriptionParam('The description product', 'This product will availabe to use'),
            'preparation_time' => getDescriptionParam('whether, How much product spend time to prepare?', 1),
            'cooking_time'     => getDescriptionParam('whether, How much product spend time to cooking?', 15),
        );
        
        return $data;
    }
}
