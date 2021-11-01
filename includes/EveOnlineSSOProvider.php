<?php

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * Based on https://github.com/killmails/oauth2-eve/ and recreated in this project for security reasons
 */
class EveOnlineSSOProvider extends AbstractProvider
{

    use BearerAuthorizationTrait;

    /**
     * Domain
     *
     * @var string
     */
    protected $domain = 'https://login.eveonline.com';

    /**
     * Get authorization url to begin OAuth flow.
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->domain . '/v2/oauth/authorize';
    }

    /**
     * Get access token url to retrieve token.
     *
     * @param  array $params
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->domain . '/v2/oauth/token';
    }

    /**
     * Get provider url to fetch user details.
     *
     * @param  AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        /**
         * CCP deprecated this endpoint in favor of JWT tokens:
         * > The oauth/verify endpoint will also be deprecated, since the v2 endpoints return a JWT token,
         * > enabling your applications to validate the JWT tokens without having to make a request to the SSO for each token.
         * > Applications can fetch the required EVE SSO metadata from https://login.eveonline.com/.well-known/oauth-authorization-server 
         * > to be able to validate JWT token signatures client-side.
         * 
         * see also https://docs.esi.evetech.net/docs/sso/validating_eve_jwt.html
         */
        return null;
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [];
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string Scope separator
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     *
     * @param  ResponseInterface $response
     * @param  array|string      $data Parsed response data
     *
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error'])) {
            throw new IdentityProviderException($data['error_description'], $response->getStatusCode(), $data);
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param  array       $response
     * @param  AccessToken $token
     *
     * @return ResourceOwnerInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        // Retrieve additional information about the Character from ESI
        $characterInfo = $this->parseJson(
            $this->getHttpClient()->request('get', 'https://esi.evetech.net/v5/characters/'.$response['CharacterID'] .'/')
                                  ->getBody()
                                  ->getContents()
        );

        return new EveOnlineSSOResourceOwner($response, $characterInfo);
    }

    public function getResourceOwner(AccessToken $token)
    {
            $jwtexplode=json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.',$token )[1]))));
            $charactername=$jwtexplode->name;
            $characterid=explode(":",$jwtexplode->sub)[2];
            $response['CharacterName']=$charactername;
            $response['CharacterID']=$characterid;
            $response['CharacterOwnerHash']=$jwtexplode->owner;
            $response['ExpiresOn']=date('Y-m-d\TH:i:s',$jwtexplode->exp);
            $response['Scopes']=implode(" ",$jwtexplode->scp);
            return $this->createResourceOwner($response, $token);
    }
}
