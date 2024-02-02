<?php

namespace App\Services\Jwt;

use App\Models\Contact;
use App\Enums\Constants;
use App\Services\SVContact;
use Illuminate\Http\Response;
use App\Http\Tools\ParamTools;
use App\Exceptions\POSException;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\RestaurantResource;
use Illuminate\Support\Facades\DB;

class BackOfficeLogin implements IJWTContract
{

    public function getRules()
    {
        return array(
            'credential.email' => 'required|email',
            'credential.password' => 'required|min:4',
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
        if ($user->account->is_default) {
            return $this->generateToken($authManager, $login_type, $user);
        }
        /**
         * Blocked Venue validation
         */
        blockedUserValidation($user);

        return $this->generateSwictAbleToken($authManager, $login_type, $user);
    }

    /**
     * This token is use only for login with email and password
     * Then only use to switch restuarant to have a full TOKEN
     */
    function generateSwictAbleToken($authManager, $login_type, $user)
    {
        if (empty($user->account->db_name)) {
            throw new POSException("We are preparing your account. Please wait a few minutes.", Constants::ERROR_CODE_GENERIC_ERROR, [], Response::HTTP_NOT_FOUND);
        }
        ParamTools::reconnectDB($user->account->db_name);

        $user = Contact::where("global_id", $user->global_id)->first();

        // Generate token
        $token = $authManager->createSwitchAbleToken($login_type, $user);

        // Get user default
        $isUserDefault = $user->account->is_default;

        // Get restaurant are logged by super admin no need return restaurant
        $restaurants = $isUserDefault ? [] : RestaurantResource::collection((new SVContact)->getRestaurants($user));

        return [
            "is_default"    => $isUserDefault,
            "token"         => $token,
            "restaurants"   => $restaurants
        ];
    }

    /**
     * @Override
     */
    public function generateToken($authManager, $login_type, $user, $extra = [], $refresh = false)
    {
        ParamTools::reconnectDB($user->account->db_name);

        $user = Contact::where("global_id", $user->global_id)->first();
        // Generate token
        $token = $authManager->createJwtToken($login_type, $user, $extra);
        // Account default
        $isUserDefault = $user->account->is_default;

        // Setup refresh token on superadmin
        $refreshToken = $authManager->createJwtRefreshToken($login_type, $user, $extra);

        //create exchange token for booking admin
        $exchange_token = $authManager->createJwtExchangeToken($login_type, $user);

        // Create user session for superadmin
        $authManager->createUserSession($user, $refreshToken, Constants::LOGIN_TYPE_BACKOFFICE, null, $exchange_token);

        // add necessary data for bearer token
        if(!$refresh){
            $user_permissions = $isUserDefault ? [] : (new SVContact)->getSelfPermission($user->id, $user->account->id);

            // Get restaurant are logged by superadmin no need return resturant
            $restaurants = $isUserDefault ? [] : RestaurantResource::collection((new SVContact)->getRestaurants($user));
            
            $data = [
                "is_default"     => $isUserDefault,
                "token"          => $token,
                "refresh_token"  => $refreshToken,
                "exchange_token" => $exchange_token,
                'permissions'    => $user_permissions,
                'restaurants'    => $restaurants
            ];
        }else{
            $data = [
                "is_default"     => $isUserDefault,
                "token"          => $token,
                "refresh_token"  => $refreshToken,
                "exchange_token" => $exchange_token
            ];
        }
        return $data;
    }

    public function generateTokenFromExchangeToken($authManager, $login_type, $user, $extra = [])
    {
        ParamTools::reconnectDB($user->account->db_name);

        // add rest gid to extra data
        $rest = DB::table('account')->where([['id', $user['account_id']], ['deleted_at', null]])->first();
        $extra['restaurant_gid'] = $rest->global_id;

        $user = Contact::where("global_id", $user->global_id)->first();
        // Generate token
        $token = $authManager->createJwtToken($login_type, $user, $extra);
        // Account default
        $isUserDefault = $user->account->is_default;

        // Setup refresh token on superadmin
        $refreshToken = $authManager->createJwtRefreshToken($login_type, $user, $extra);

        // Create user session for superadmin
        $authManager->createUserSession($user, $refreshToken, Constants::LOGIN_TYPE_BACKOFFICE);

        return [
            "is_default"     => $isUserDefault,
            "token"          => $token,
            "refresh_token"  => $refreshToken
        ];
    }

    public function logout(array $params) : mixed 
    {
        // Code ...
        return "Logout Successfully!";
    }

    public function getRefreshToken(JWTManager $authManager, $data){}
}
