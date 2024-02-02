<?php

namespace App\Services\Jwt;

use App\Enums\Constants;
use App\Services\SVContact;
use Illuminate\Http\Response;
use App\Services\SVAppLicense;
use App\Exceptions\POSException;
use App\Http\Tools\ParamTools;
use Illuminate\Support\Facades\Hash;

class AppManagerLogin implements IJWTContract
{
    public function getRules()
    {
        return array(
            'credential.email'        => 'required|email',
            'credential.password'     => 'required|string'
        );
    }

    /**
     * @override
     */
    function authenticateAndGetToken($data, $authManager)
    {
        $email      = $data['credential']['email'];
        $password   = $data['credential']['password'];
        $login_type = $data['login_type'];

        $user = (new SVContact())->getByEmail($email);
        if (!isset($user) || !Hash::check($password, $user->password)) {
            throw new POSException('Incorrect email or password', "WRONG_PASSWORD", [], Response::HTTP_BAD_REQUEST);
        }

        /* Prevent case user link to restaurant not account */
        $account = $user->account;
        $account_id = $account->parent_id ? $account->parent_id : $account->id;

        ParamTools::reconnectDB($user->account->db_name);

        $user = (new SVContact)->getByGId($user->global_id);

        $extra = array(
            'db_name'        => $user->account->db_name,
            'restaurant_gid' => "302c588c-9987-3ae0-8a83-43d7ca3890d9"
        );

        return $this->generateToken($authManager, $login_type, $user, $extra);
    }

    /**
     * @override
     */
    function generateToken(JWTManager $authManager, $authType, $user, $extra = [])
    {
        if (getUserLastConnection($user)) {
            $extra['last_connection'] = getUserLastConnection($user);
        }
        $expiredAt = now()->addMinutes(1440);
        $token = $authManager->createJwtToken($authType, $user, $extra, 1440);
        $refreshToken = $authManager->createJwtRefreshToken($authType, $user, $extra, $expiredAt);
        $authManager->createUserSession($user, $refreshToken, Constants::APP_TYPE_APP_MANAGER);

        return [
            "expired_at" => $expiredAt,
            "token" => $token,
            "refresh_token" => $refreshToken,
        ];
    }

    /**
     * Logout
     *  
     * @param array $params The request all
     * 
     * @return mixed
     */
    public function logout(array $params) : mixed
    {
        $data = "You have logout successfully";
        return $data;
    }

    public function getRefreshToken(JWTManager $authManager, $data){}
}
