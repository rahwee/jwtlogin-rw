<?php

namespace App\Services\Jwt;

interface IJWTContract
{
    /**
     * Use to authenticate user then generate token
     * by using function generateToken
     */
    function authenticateAndGetToken($data, JWTManager $authManager);

    /**
     * Use to Generate Token
     * a standard function generate standard response for each auth type
     */
    function generateToken(JWTManager $authManager, $authType, $user, array $extras = []);

    function getRefreshToken(JWTManager $authManager, $data);

    /**
     * Logout
     * 
     * Use to logout for each auth type
     * 
     * @param array $params The all body request
     * @return mixed
     * */ 
    function logout(array $params) : mixed;
}
