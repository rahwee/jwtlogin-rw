<?php

namespace App\Services\Auth;

use App\Enums\Constants;
use App\Exceptions\POSException;
use App\Http\Tools\ParamTools;
use App\Models\GuestSession;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\{Guard, UserProvider};
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;

/**
 * Class JwtGuard
 * @package App\Services
 */
class JwtGuard implements Guard
{
    use GuardHelpers;

    /**
     * @var Request
     */
    private $request;

    private $lastAttempted;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    public function user()
    {
        if (!is_null($this->user)) {
            return $this->user;
        }

        return $this->user = $this->authenticateByToken();
    }

    public function validate(array $credentials = [])
    {
        if (!$credentials) {
            return false;
        }


        if ($this->provider->retrieveByCredentials($credentials)) {
            return true;
        }

        return false;
    }

    protected function authenticateByToken()
    {
        if (!empty($this->user)) {
            return $this->user;
        }

        $token = $this->getBearerToken();

        $user = null;

        if (empty($token)) {
            return $user;
        }


        try {

            $decoded = $this->authenticatedAccessToken($token);

            if (!$decoded) {
                return $user;
            }

            $token_type = $decoded->getTokenType();

            // Auth Type Customer menu
            $auth_type = $decoded->getAuthType();

            if ($token_type == Constants::TOKEN_TYPE_BEARER )
            {
                ParamTools::reconnectDB($decoded->getDbName());
                if ($auth_type == Constants::LOGIN_TYPE_CUSTOMER_MENU)
                {
                    $user = GuestSession::where('global_id', $decoded->getSessionGlobalId())->first(); 
                }
                else 
                {
                    $user = $this->provider->retrieveById($decoded->getRelatedTo());
                    $user->restaurant_gid = $decoded->getRestaurantId();
                    $user->auth_type      = $auth_type;
                }
            }
        }
        catch (\Exception $exception)
        {
            // logger($exception);
            $user = null;
            throw new \Illuminate\Auth\AuthenticationException($exception->getMessage());
        } catch (ExpiredException $exception) {
            // logger($exception);
            $user = null;
            throw new \Illuminate\Auth\AuthenticationException($exception->getMessage());
        }

        return $user;
    }

    protected function getBearerToken()
    {
        return $this->request->bearerToken();
    }

    public function attempt(array $credentials = [], $login = true)
    {
        $this->lastAttempted = $user = $this->provider->retrieveByCredentials($credentials);

        if ($this->hasValidCredentials($user, $credentials)) {
            $this->user = $user;
            return true;
        }

        return false;
    }

    protected function hasValidCredentials($user, $credentials)
    {
        return $user !== null && $this->provider->validateCredentials($user, $credentials);
    }

    public function authenticatedAccessToken($token)
    {
        return JwtParser::loadFromToken($token);
    }
}
