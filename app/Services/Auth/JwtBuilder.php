<?php

namespace App\Services\Auth;
use Carbon\CarbonInterface;
use Firebase\JWT\JWT;
/**
 * Class JwtBuilder
 * @package App\Services
 */
class JwtBuilder
{
    protected $claims;

    /**
     *  The "iss" (issuer) claim identifies the principal that issued the
     *  JWT.  The processing of this claim is generally application specific.
     *  The "iss" value is a case-sensitive string containing a StringOrURI
     *  value.  Use of this claim is OPTIONAL.
     *
     * @param $val
     * @return $this
     */
    public function issuedBy($val): self
    {
        return $this->registerClaim('iss', $val);
    }

    /**
     * The iat (issued at) claim identifies the time at which the JWT was issued.
     * This claim can be used to determine the age of the JWT.
     * Its value MUST be a number containing a NumericDate value. Use of this claim is OPTIONAL.
     *
     * @param $val
     * @return $this
     */
    public function issuedAt(CarbonInterface $dateTime)
    {
        return $this->registerClaim('iat', $dateTime->timestamp);
    }

    /**
     *  The "sub" (subject) claim identifies the principal that is the
     *  subject of the JWT.  The claims in a JWT are normally statements
     *  about the subject.  The subject value MUST either be scoped to be
     *  locally unique in the context of the issuer or be globally unique.
     *  The processing of this claim is generally application specific.  The
     *  "*sub" value is a case-sensitive string containing a StringOrURI
     *  value.  Use of this claim is OPTIONAL.
     *
     * @param $val
     * @return $this
     */
    public function relatedTo($val)
    {
        return $this->registerClaim('sub', $val);
    }

    /**
     * The aud (audience) claim identifies the recipients that the JWT is intended for.
     * Each principal intended to process the JWT MUST identify itself with a value in the audience claim.
     * If the principal processing the claim does not identify itself with a value in the aud claim when this
     * claim is present, then the JWT MUST be rejected. In the general case, the aud value is an array
     * of case-sensitive strings, each containing a StringOrURI value.
     * In the special case when the JWT has one audience, the aud value MAY be a single case-sensitive string
     * containing a StringOrURI value. The interpretation of audience values is generally application specific.
     * Use of this claim is OPTIONAL.
     *
     * @param $name
     * @return $this
     */
    public function audience($name)
    {
        return $this->registerClaim('aud', $name);
    }

    /**
     * The exp (expiration time) claim identifies the expiration time on or after which the JWT MUST NOT be accepted
     * for processing.
     * The processing of the exp claim requires that the current date/time MUST be before the expiration date/time
     * listed in the exp claim. Implementers MAY provide for some small leeway, usually no more than a few minutes,
     * to account for clock skew. Its value MUST be a number containing a NumericDate value. Use of this claim is OPTIONAL.
     *
     * @param CarbonInterface $dateTime
     * @return $this
     */
    public function expiresAt(CarbonInterface $dateTime)
    {
        return $this->registerClaim('exp', $dateTime->timestamp);
    }

    /**
     * The jti (JWT ID) claim provides a unique identifier for the JWT.
     * The identifier value MUST be assigned in a manner that ensures that there is a negligible probability that the
     * same value will be accidentally assigned to a different data object; if the application uses multiple issuers,
     * collisions MUST be prevented among values produced by different issuers as well. The jti claim can be used to
     * prevent the JWT from being replayed. The jti value is a case-sensitive string. Use of this claim is OPTIONAL.
     *
     * @param $val
     * @return $this
     */
    public function identifiedBy($val)
    {
        return $this->registerClaim('jti', $val);
    }

    /**
     * The nbf (not before) claim identifies the time before which the JWT MUST NOT be accepted for processing.
     * The processing of the nbf claim requires that the current date/time MUST be after or equal to the not-before
     * date/time listed in the nbf claim. Implementers MAY provide for some small leeway, usually no more than a
     * few minutes, to account for clock skew. Its value MUST be a number containing a NumericDate value.
     * Use of this claim is OPTIONAL.
     *
     * @param CarbonInterface $carbon
     * @return $this
     */
    public function canOnlyBeUsedAfter(CarbonInterface $carbon)
    {
        return $this->registerClaim('nbf', $carbon->timestamp);
    }

    public function withClaim($name, $value)
    {
        return $this->registerClaim($name, $value);
    }

    public function withClaims(array $claims): self
    {
        foreach ($claims as $name => $value) {
            $this->withClaim($name, $value);
        }
        return $this;
    }

    public function getToken()
    {
        // dd($this->claims, $this->getPrivateKey(), $this->getAlgo());
        return JWT::encode($this->claims, $this->getPrivateKey(), $this->getAlgo());
    }

    function getPrivateKey(): string
    {
        // return file_get_contents(config('jwt.private_key'));
        return config('jwt.secret');
    }

    function getAlgo()
    {
        return config('jwt.encrypt_algo');
    }

    public function setDbName(string $val): self
    {
        return $this->registerClaim('db_name', $val);
    }

    public function setAuthType(string $val): self
    {
        return $this->registerClaim('auth_type', $val);
    }

    public function setTokenType(string $val): self
    {
        return $this->registerClaim('type', $val);
    }

    public function setUniqid(string $val): self
    {
        return $this->registerClaim('N', $val);
    }

    public function setRestaurantId(string $val): self
    {
        return $this->registerClaim('restaurant_gid', $val);
    }

    protected function registerClaim(string $name, string $val): self
    {
        $this->claims[$name] = $val;
        return $this;
    }
}
