<?php

namespace App\Services\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Class JwtParser
 * @package App\Services
 */
class JwtParser
{
    /**
     * @var array|object
     */
    protected $claims;

    public function __construct(string $token)
    {
        JWT::$leeway = $this->getLeeway();
        $this->claims = JWT::decode($token, new Key($this->getPublicKey(), $this->supportedAlgos()));
    }

    public static function loadFromToken(string $token)
    {
        return new self($token);
    }

    public function getAll()
    {
        return $this->claims;
    }

    public function getIssuedBy()
    {
        return $this->getClaim('iss');
    }

    public function getIssuedAt()
    {
        return $this->getClaim('iat');
    }

    /**
     * Get related to
     * sub: user global_id
     * */ 
    public function getRelatedTo()
    {
        return $this->getClaim('sub');
    }

    public function getRestaurantId()
    {
        return $this->getClaim('restaurant_gid');
    }

    public function getKdsGid()
    {
        return $this->getClaim('kds_gid');
    }

    public function getDeviceGid()
    {
        return $this->getClaim('device_gid');
    }

    public function getAudience()
    {
        return $this->getClaim('aud');
    }

    public function getExpiresAt()
    {
        return $this->getClaim('exp');
    }

    public function getIdentifiedBy()
    {
        return $this->getClaim('jti');
    }

    public function getCanOnlyBeUsedAfter()
    {
        return $this->getClaim('nbf');
    }

    public function getDbName() 
    {
        return $this->getClaim('db_name');
    }

    public function getTokenType() 
    {
        return $this->getClaim('type');
    }

    public function getAuthType()
    {
        return $this->getClaim('auth_type');
    }

    public function getSessionGlobalId()
    {
        return $this->getClaim('user_gid');
    }

    protected function getClaim(string $name)
    {
        return $this->claims->{$name} ?? null;
    }

    protected function getPublicKey(): string
    {
        return config('jwt.secret');
    }

    protected function getAlgo()
    {
        return config('jwt.encrypt_algo');
    }

    protected function getLeeway()
    {
        return config('jwt.leeway');
    }

    protected function supportedAlgos()
    {
        return config('jwt.encrypt_algo');
    }

}
