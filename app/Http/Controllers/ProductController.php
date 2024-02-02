<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use App\Services\SVProduct;

class ProductController extends BaseApi
{
    public function getService()
    {
        return new SVProduct();
    }

    public function index(StoreProductRequest $request)
    {
        DB::beginTransaction();
        $service = $this->getService();
        try{
            $data = $service->index($request->all());
            return new ProductResource($data);

        }catch(\Throwable $th)
        {
            DB::rollBack();
            return $this->respondError($th);
        }
    }
}
