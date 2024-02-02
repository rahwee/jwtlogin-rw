<?php

namespace App\Services\Jwt;

use App\Enums\Constants;
use App\Exceptions\POSException;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\UserSession;
use App\Services\Auth\JwtBuilder;
use App\Services\SVContact;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * Class JwtAuth
 * @package App\Services
 */
class JwtAuth
{
    private $restaurant_id = null;

    public function __construct($restaurant_id = null)
    {
        $this->restaurant_id = $restaurant_id;
    }

    public function authenticateAndReturnJwtToken(string $email, string $password): ?array
    {
        $user = (new SVContact())->getByEmail($email);
        if (!isset($user) || (isset($user) && !Hash::check($password, $user->password))) {
            throw new POSException('Incorrect email or password', "WRONG_PASSWORD", [], Response::HTTP_BAD_REQUEST);
        }
        return $this->generateToken($user);
    }

    public function generateToken($user): array
    {
        $data = array(
            "access_token"  => $this->createJwtToken($user),
            "refresh_token" => $this->createJwtRefreshToken($user),
        );
        UserSession::create(array(
            'user_id' => $user->id,
            'refresh_token' => $data['refresh_token'],
            'is_refresh' => 0
        ));
        return $data;
    }

    /**
     * Create bearer token
     * */
    protected function createJwtToken(Contact $user, CarbonInterface $ttl = null): string
    {
        $exp = $ttl ?? now()->addMinutes(config('jwt.ttl'));
        $jwtBuilder = new JwtBuilder();
        return $this->getSetupJwtBuilder($jwtBuilder, $user, $exp, Constants::TOKEN_TYPE_BEARER);
    }

    /**
     * Create refresh token
     * */
    protected function createJwtRefreshToken(Contact $user, CarbonInterface $ttl = null): string
    {
        $exp = $ttl ?? now()->addMinutes(config('jwt.refresh_ttl'));
        $jwtBuilder = new JwtBuilder();
        return $this->getSetupJwtBuilder($jwtBuilder, $user, $exp, Constants::TOKEN_TYPE_REFRESH);
    }

    /**
     * Get setup builder types
     * */
    public function getSetupJwtBuilder(JwtBuilder $builder, $user, $exp, $token_type)
    {
        return $builder->setUniqid(uniqid(gmdate("YmdHis")))
            ->setTokenType($token_type)
            ->setDbName($user->account->db_name)
            ->setRestaurantId($this->restaurant_id)
            ->issuedBy(config('app.url'))
            ->audience(config('app.name'))
            ->issuedAt(now())
            ->expiresAt($exp)
            ->relatedTo($user->global_id)
            ->getToken();
    }
}
