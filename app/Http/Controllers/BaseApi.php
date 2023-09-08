<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Exceptions\POSException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseApi
{
    protected function getService() {
        return null;
    }

    protected function respondError($error) {
        $status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
        $ret = new \stdClass();
        $ret->success = false;
        $msg = $error->getMessage();

        if ($error instanceof POSException ) {
            $ret->code = $error->getStatusCode();
            $ret->error_code = $error->getErrorCode();
            $ret->message = $msg;

            $status_code = $error->getStatusCode();
        }
        else if ($error instanceof ModelNotFoundException) {
            $model       = $error->getModel();
            $replace     = "App\\Models\\";
            $ret->code       = Response::HTTP_NOT_FOUND;
            $ret->message    = 'Record for '. str_replace($replace,'', $model) .' not found';
            $ret->error_code = 'GENERIC_ERROR';
            $msg = $ret->message;
            $status_code = Response::HTTP_NOT_FOUND;
        }
        else {
            $ret->code       = Response::HTTP_INTERNAL_SERVER_ERROR;
            $ret->error_code = $error->getCode() ?? 'GENERIC_ERROR';
            $ret->message    = $msg;
        }

        // In back office need check this one
        $ret->error_message = $msg;

        if (config('app.env') != 'production')
        {
            $ret->data = [
                "line"          => $error->getLine(),
                "file"          => $error->getFile(),
                "trace"         => $error->getTrace()[0]
            ];
        }

        return response()->json($ret, $status_code);
    }

    protected function respondSuccess($data, $extra = [], $delay = 0) {

        $ret = new \stdClass();
        $ret->success = true;
        $ret->code = 200;
        $ret->error_code = "";
        $ret->message = "success";
        $ret->data = $data;

        foreach ($extra as $key => $val) {
            $ret->{$key} = $val;
        }
        return response()->json($ret);
    }
}
