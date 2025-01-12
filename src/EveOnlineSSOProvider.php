<?php

declare(strict_types=1);

namespace MediaWiki\Extension\MWEVESSO;

use Exception;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use MediaWiki\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

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
     * Logger
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    public function __construct(array $opts = [], array $colls = [])
    {
        $this->logger = LoggerFactory::getInstance('EveOnlineSSOProvider');
        parent::__construct($opts, $colls);
    }

    /**
     * Get authorization url to begin OAuth flow.
     *
     * @return string
     */
    public function getBaseAuthorizationUrl(): string
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
    public function getBaseAccessTokenUrl(array $params): string
    {
        return $this->domain . '/v2/oauth/token';
    }

    /**
     * Get provider url to fetch user details.
     *
     * @param  AccessTokenInterface $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessTokenInterface $token): string
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
        return '';
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes(): array
    {
        return [];
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string Scope separator
     */
    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     *
     * @param ResponseInterface $response
     * @param mixed $data Parsed response data
     *
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, mixed $data): void
    {
        if (is_array($data) && !empty($data['error'])) {
            $description = empty($data['error_description']) ? '' : strval($data['error_description']);
            throw new IdentityProviderException(
                sprintf('Error: %s. %s', strval($data['error']), $description),
                $response->getStatusCode(),
                $data
            );
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param  array       $response
     * @param  AccessTokenInterface $token
     *
     * @return ResourceOwnerInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \InvalidArgumentException
     */
    protected function createResourceOwner(
        array $response,
        AccessTokenInterface $token
    ): ResourceOwnerInterface {
        // Retrieve additional information about the Character from ESI
        $characterInfo = [];
        if (empty($response['CharacterID'])) {
            $this->logger->error(
                sprintf('Missing CharacterID in response: %s', json_encode($response))
            );
            throw new InvalidArgumentException('Missing CharacterID in response');
        }
        try {
            // this endpoint is updated once an hour
            $newresponse = $this->getHttpClient()->request(
                'POST',
                'https://esi.evetech.net/latest/characters/affiliation/',
                ['json' => [$response['CharacterID']]]
            );
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error retrieving character affiliation from ESI: %s', $e->getMessage())
            );
            throw $e;
        }
        $charAffiliationAll = $this->parseJson(
            $newresponse->getBody()->getContents()
        );
        $count = count($charAffiliationAll);
        if ($count === 1) {
            $characterInfo = reset($charAffiliationAll);
            $this->logger->debug(
                sprintf('Character info: %s', json_encode($characterInfo))
            );
            if (!is_array($characterInfo)) {
                $this->logger->error(
                    sprintf('Invalid character info: %s', json_encode($characterInfo))
                );
                throw new InvalidArgumentException('Invalid character info');
            }
        } else {
            $characterInfo = [];
            $this->logger->error(
                sprintf('Found %d affiliations: %s', $count, json_encode($characterInfo))
            );
            throw new InvalidArgumentException('Found multiple affiliations for character');
        }
        return new EveOnlineSSOResourceOwner($response, $characterInfo);
    }

    /**
     * Get the resource owner from a given response.
     * !!!WARNING!!! This method is very, very magical
     * TODO: need to work out how to make it more simple
     *
     * @param AccessTokenInterface $token
     *   The access token
     *
     * @return ResourceOwnerInterface
     *   The resource owner
     */
    public function getResourceOwner(AccessTokenInterface $token): ResourceOwnerInterface
    {
        $split_token = explode('.', (string) $token);
        /** @var object $jwtexplode */
        $jwtexplode = json_decode(
            base64_decode(
                str_replace(
                    '_',
                    '/',
                    str_replace('-', '+', $split_token[1] ?? '')
                )
            )
        );
        $charactername = $jwtexplode->name ?? '';
        $characterid = explode(":", strval($jwtexplode->sub ?? ''))[2] ?? '';
        $response = [];
        $response['CharacterName'] = $charactername;
        $response['CharacterID'] = $characterid;
        $response['CharacterOwnerHash'] = $jwtexplode->owner ?? '';
        $response['ExpiresOn'] = date('Y-m-d\TH:i:s', intval($jwtexplode->exp ?? 0));
        //scp no longer seems to be returned, not sure why
        //$response['Scopes']=implode(" ",$jwtexplode->scp);
        $response['Scopes'] = "";
        return $this->createResourceOwner($response, $token);
    }
}
