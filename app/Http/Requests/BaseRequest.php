<?php

namespace App\Http\Requests;

use App\Http\Tools\ParamTools;
use App\Models\Language;
use App\Services\SVLocalize;
use App\Services\SVMeta;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    function getService()
    {
        return null;
    }

    protected function isUserHasPermission() 
    {
        $user = $this->user();
        return $user->can('user_permission');
    }


    protected function getRulePaginate() 
    {
        return array(
            "size" => 'nullable|integer',
            "order" => 'nullable|string',
            "sort" => 'nullable|string',
            "s" => 'nullable|string',
            "page" => 'nullable|integer',
        );
    }

    public function messages()
    {
        return array();
    }

    protected function prepareForValidation()
    {

    }
}
