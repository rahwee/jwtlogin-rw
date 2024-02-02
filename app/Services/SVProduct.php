<?php

namespace App\Services;

use App\Services\BaseService;
use Illuminate\Validation\Rule;

class SVProduct extends BaseService
{
    public function getRules()
    {
        return [
            'name' => 'required|string',
            'image_url'                                            => 'nullable|string',
            'dish_type'                                            => ['required', Rule::in(['DISH', 'FORMULA', 'ADD_ON', 'LOYALTY_TOP_UP', 'LOYALTY_TOP_UP_AMOUNT'])],
            'unit_measurement'                                     => ['required', Rule::in(['QUANTITY','SCALE'])],
            'quantity'                                             => 'nullable|integer',
            'preparation_time'                                     => 'nullable|integer',
            'cooking_time'                                         => 'nullable|integer',
            'dish_code'                                            => 'nullable|string',
            'categories'                                           => 'nullable|array',
            'categories.*'                                         => 'required|uuid',
            'tags'                                                 => 'nullable|array',
            'tags.*.global_id'                                     => 'nullable|uuid',
            'tags.*.label'                                         => 'nullable|string',
            'active'                                               => 'nullable|boolean',
            'description'                                          => 'nullable|string',
            'price_list'                                           => 'nullable|array',
            'price_list.*.price'                                   => 'nullable|numeric',
            'price_list.*.restaurant_id'                           => 'nullable|uuid',
            'price_list.*.tax_id'                                  => 'required|uuid',
            'pricebooks'                                           => 'nullable|array',
            'pricebooks.*.global_id'                               => 'required|uuid',
            'pricebooks.*.price'                                   => 'nullable|numeric|max:99999999',
            'pricebooks.*.override_tax_id'                         => 'nullable|uuid',
            'pricebooks.*.active'                                  => 'required|boolean',
            'restaurant_pricebooks'                                => 'nullable|array',
            'restaurant_pricebooks.*.restaurant_id'                => 'required|uuid',
            'restaurant_pricebooks.*.pricebooks'                   => 'required|array',
            'restaurant_pricebooks.*.pricebooks.*.price'           => 'nullable|numeric',
            'restaurant_pricebooks.*.pricebooks.*.global_id'       => 'required|uuid',
            'restaurant_pricebooks.*.pricebooks.*.override_tax_id' => 'nullable|uuid',
            'restaurant_pricebooks.*.pricebooks.*.active'          => 'required|boolean',
        ];
    }

    public function index($params)
    {
        dd($params);
    }

}