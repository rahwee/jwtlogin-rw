<?php

namespace App\Http\Controllers;

use Exception;
use App\Enums\Constants;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Exceptions\POSException;
use App\Services\Jwt\JWTManager;
use App\Http\Requests\GenericLogin;

class AuthController extends BaseApi
{
    public function login(GenericLogin $request)
    {
        try {
            $authManager = new JWTManager(strtoupper($request->get('login_type')));
            $authService = $authManager->getService();
            if ($authService) {
                $token = $authService->authenticateAndGetToken($request->all(), $authManager);
                return $this->respondSuccess($token);
            } else {
                throw new POSException("Invalid Login Type", Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $th) {
            return $this->respondError($th);
        }
    }
}
