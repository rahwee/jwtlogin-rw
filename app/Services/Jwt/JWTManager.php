<?php

namespace App\Services\Jwt;

use App\Enums\Constants;
use App\Exceptions\POSException;
use App\Http\Tools\Factory;
use App\Models\Contact;
use App\Models\UserSession;
use App\Services\Auth\JwtBuilder;
use App\Services\SVContact;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class JWTManager
{
    private $loginType;
    private $service;
    private $path = "App\\Services\\Jwt";

    /**
     * List of available login type
     * class: use to create login class
     * switch: for switching restaurant
     */
    private $typeList = [
        Constants::LOGIN_TYPE_BACKOFFICE    => ["class" => "BackOffice",    "switch" => true],
        Constants::LOGIN_TYPE_POS           => ["class" => "POS",           "switch" => true],
        Constants::LOGIN_TYPE_EMAIL         => ["class" => "Email",         "switch" => false],
        Constants::LOGIN_TYPE_MPOS          => ["class" => "MPOS",          "switch" => false],
        Constants::LOGIN_TYPE_KIOSK         => ["class" => "KIOSK",         "switch" => false],
        Constants::LOGIN_TYPE_CUSTOMER_MENU => ["class" => "CustomerMenu",  "switch" => false],
        Constants::LOGIN_TYPE_BUTLER_CLIENT => ["class" => "ButlerClient",  "switch" => false],
        Constants::LOGIN_TYPE_BUTLER_USER   => ["class" => "ButlerUser",    "switch" => false],
        Constants::LOGIN_TYPE_APP_MANAGER   => ["class" => "AppManager",    "switch" => false],
        Constants::LOGIN_TYPE_KDS           => ["class" => "KDS",           "switch" => true],
    ];

    public function __construct($loginType = null)
    {
        $this->loginType = $loginType;
    }

    /**
     * Get list of app type
     * @return array
     * */
    public function getListTypeApp(): array
    {
        return array_keys($this->typeList);
    }

    /**
     * Service to construct Login Type
     */
    public function getService(): ?IJWTContract
    {
        try {
            if (array_key_exists($this->loginType, $this->typeList)) {
                $factory = new Factory($this->path);
                $this->service = $factory->make($this->typeList[$this->loginType]["class"] . "Login");
                if (!$this->service) return null;

                return $this->service;
            }

            return null;
        } catch (Exception $ex) {
            throw $ex;
            return null;
        }
    }

    /**
     * Get type list specific by key
     * @param string $key
     * */
    public function getTypeList($key): array
    {
        return $this->typeList[$key] ?? [];
    }

    /**
     * isSwitchAble
     */
    public function isSwitchAble($loginType)
    {
        $login_type = $this->getTypeList($loginType);
        if (!($login_type['switch'] ?? false)) {
            throw new POSException('This token can\'t switch restaurant.', Constants::ERROR_WRONG_REQUEST, [], Response::HTTP_BAD_REQUEST);
        }

        return true;
    }


    /**
     * Refresh token by superadmin
     * */
    public function getRefreshTokenSuperAdmin($user)
    {

        $paramsTokens = array($this->loginType, $user);
        $token        = $this->createJwtToken(...$paramsTokens);
        $refreshToken = $this->createJwtRefreshToken(...$paramsTokens);
        $this->createUserSession($user, $refreshToken, Constants::LOGIN_TYPE_BACKOFFICE);

        return [
            "token"         => $token,
            "refresh_token" => $refreshToken,
            'permissions'   => []
        ];
    }

    /**
     * Create user session
     * */
    public function createUserSession($user, $refreshToken, $app_type, $token = null, $exchange_token = null)
    {
        $app_type = DB::table('meta')->where([
            ['key', Constants::LOGIN_KEY_APP_TYPE],
            ['value', $app_type]
        ])->first();
        
        // Save user session
        UserSession::create(array(
            'user_id'        => $user->id,
            'app_type_id'    => $app_type ? $app_type->id : null,
            'access_token'   => $token,
            'refresh_token'  => $refreshToken,
            'is_refresh'     => 0,
            'exchange_token' => $exchange_token
        ));
    }

    /**
     * Switchable Token is using only for switching the rest
     * */
    public function createSwitchAbleToken(string $loginType, Contact $user = null, array $extraData = [], $ttl = null): string
    {
        // set fix to 3 minutes
        $exp = $ttl ? now()->addMinutes(intval($ttl)) : now()->addMinutes(3);
        return $this->getSetupJwtBuilder($user, $loginType, $exp, Constants::TOKEN_TYPE_SWITCH, $extraData);
    }

    /**
     * Base function for generate standard JWT Token
     * more data can be added in $extraData
     * */
    public function createJwtToken(string $loginType, Contact $user = null, array $extraData = [], $ttl = null): string
    {
        $exp = $ttl ? now()->addMinutes(intval($ttl)) : now()->addMinutes(config('jwt.ttl'));
        $exp = $ttl < 0 ? null : $exp;
        return $this->getSetupJwtBuilder($user, $loginType, $exp, Constants::TOKEN_TYPE_BEARER, $extraData);
    }

    /**
     * Base function for generate standard Refresh Token
     * more data can be added in $extraData
     * When using refresh token please include with IJWTContractinType, $exp, Constants::TOKEN_TYPE_REFRESH, $extraData);\
     * */
    public function createJwtRefreshToken(string $loginType, Contact $user = null, array $extraData = [], CarbonInterface $ttl = null): string
    {
        $exp = $ttl ?? now()->addMinutes(config('jwt.refresh_ttl'));
        return $this->getSetupJwtBuilder($user, $loginType, $exp, Constants::TOKEN_TYPE_REFRESH, $extraData);
    }

    /**
     * Get setup builder types
     * */
    public function getSetupJwtBuilder(Contact $user = null, string $loginType, $exp, string $token_type, array $extraData = [])
    {
        $builder = new JwtBuilder();
        $builder->setUniqid(uniqid(gmdate("YmdHis")))
            ->setTokenType($token_type)
            ->setAuthType($loginType)
            ->withClaims($extraData);
        
        if ($token_type == Constants::TOKEN_TYPE_BEARER) {
            $builder->issuedBy(config('app.url'));
            $builder->audience(config('app.name'));
            $builder->issuedAt(now());
        }

        if ($exp) {
            $builder->expiresAt($exp);
        }

        if ($user) {
            $builder->setDbName($user->account->db_name);
            $builder->relatedTo(@$user->global_id);
        }

        return $builder->getToken();
    }
    public function createJwtExchangeToken(string $loginType, Contact $user = null, array $extraData = [], CarbonInterface $ttl = null): string
    {
        return $this->getSetupJwtBuilder($user, $loginType, 0, Constants::TOKEN_TYPE_EXCHANGE_TOKEN, $extraData);
    }
}
