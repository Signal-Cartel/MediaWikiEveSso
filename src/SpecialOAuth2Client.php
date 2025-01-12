<?php

declare(strict_types=1);

namespace MediaWiki\Extension\MWEVESSO;

/**
 * SpecialOAuth2Client.php
 * Based on TwitterLogin by David Raison, which is based on the guideline published by Dave Challis at http://blogs.ecs.soton.ac.uk/webteam/2010/04/13/254/
 * @license: LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html
 *
 * @file SpecialOAuth2Client.php
 * @ingroup OAuth2Client
 *
 * @author Joost de Keijzer
 * @author Nischay Nahata for Schine GmbH
 *
 * Uses the OAuth2 library https://github.com/vznet/oauth_2.0_client_php
 *
 */

use Exception;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use MWException;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class SpecialOAuth2Client extends SpecialPage
{
    protected const OAUTH_SERVISE_NAME = 'EVE Online SSO';
    /**
     * EVE Online SSO Provider
     *
     * @var EveOnlineSSOProvider
     */
    private EveOnlineSSOProvider $_provider;

    /**
     * The client ID
     *
     * @var string|null
     */
    private ?string $_clientId = null;

    /**
     * The client secret
     *
     * @var string|null
     */
    private ?string $_clientSecret = null;


    /**
     * The callback URL
     *
     * @var string
     */
    private string $_callbackUrl;

    /**
     * The allowed corporation IDs
     *
     * @var array
     */
    private array $_allowedCorporationIds = []; // @phpstan-ignore missingType.iterableValue

    /**
     * The allowed character IDs
     *
     * @var array
     */
    private array $_allowedCharacterIds = []; // @phpstan-ignore missingType.iterableValue

    /**
     * The allowed alliance IDs
     *
     * @var array
     */
    private array $_allowedAllianceIds = []; // @phpstan-ignore missingType.iterableValue

    /**
     * Required settings in global $wgOAuth2Client
     *
     * $wgOAuth2Client['client']['id']
     * $wgOAuth2Client['client']['secret']
     * $wgOAuth2Client['configuration']['allowed_alliance_ids']
     * $wgOAuth2Client['configuration']['allowed_corporation_ids']
     * $wgOAuth2Client['configuration']['allowed_character_ids']
     */
    public function __construct()
    {
        parent::__construct('OAuth2Client');
        global $wgOAuth2Client;
        if (is_array($wgOAuth2Client)) {
            if (isset($wgOAuth2Client['client'])
                && is_array($wgOAuth2Client['client'])
            ) {
                if (isset($wgOAuth2Client['client']['id'])) {
                    $this->_clientId = strval($wgOAuth2Client['client']['id']);
                }
                if (isset($wgOAuth2Client['client']['secret'])) {
                    $this->_clientSecret = strval($wgOAuth2Client['client']['secret']);
                }
            }
            if (isset($wgOAuth2Client['configuration'])
                && is_array($wgOAuth2Client['configuration'])
            ) {
                if (isset($wgOAuth2Client['configuration']['allowed_alliance_ids'])
                    && is_array($wgOAuth2Client['configuration']['allowed_alliance_ids'])
                ) {
                    $this->_allowedAllianceIds = $wgOAuth2Client['configuration']['allowed_alliance_ids'];
                }
                if (isset($wgOAuth2Client['configuration']['allowed_corporation_ids'])
                    && is_array($wgOAuth2Client['configuration']['allowed_corporation_ids'])
                ) {
                    $this->_allowedCorporationIds = $wgOAuth2Client['configuration']['allowed_corporation_ids'];
                }
                if (isset($wgOAuth2Client['configuration']['allowed_character_ids'])
                    && is_array($wgOAuth2Client['configuration']['allowed_character_ids'])
                ) {
                    $this->_allowedCharacterIds = $wgOAuth2Client['configuration']['allowed_character_ids'];
                }
            }
        }
        $this->_callbackUrl = Helper::getCallbackUrl();
        $this->_provider = new EveOnlineSSOProvider(
            [
                'clientId' => $this->_clientId, // The client ID assigned to you by the provider
                'clientSecret' => $this->_clientSecret, // The client password assigned to you by the provider
                'redirectUri' => $this->_callbackUrl
            ]
        );
    }

    /**
     * Default method being called by a specialpage
     *
     * @param mixed $parameter The parameter is optional
     *
     * @return void
     */
    public function execute(mixed $parameter = null): void
    {
        $this->setHeaders();
        switch ($parameter) {
            case 'redirect':
                try {
                    $this->_redirect();
                } catch (Exception $e) {
                    $this->_showError($e->getMessage());
                }
                break;
            case 'callback':
                try {
                    $this->_handleCallback();
                } catch (Exception $e) {
                    $this->_showError($e->getMessage());
                }
                break;
            default:
                $this->_default();
                break;
        }
    }

    /**
     * Shows the error message
     *
     * @param string $error_msg The error message
     *
     * @return void
     */
    private function _showError(string $error_msg): void
    {
        $this->getOutput()->setPagetitle(
            wfMessage('mwevesso-login-header', self::OAUTH_SERVISE_NAME)->text()
        );
        $this->getOutput()->addWikiMsg('mwevesso-error', $error_msg);
    }

    /**
     * Method for redirecting to the authorization URL
     *
     * @return void
     */
    private function _redirect(): void
    {
        $request = $this->getRequest();
        $request->getSession()->persist();
        $request->getSession()->set(
            'returnto',
            $request->getVal('returnto')
        );
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $this->_provider->getAuthorizationUrl();
        // Get the state generated for you and store it to the session.
        $request->getSession()->set(
            'oauth2state',
            $this->_provider->getState()
        );
        $request->getSession()->save();
        // Redirect the user to the authorization URL.
        $this->getOutput()->redirect($authorizationUrl);
    }

    /**
     * The callback handler
     *
     * @return void
     * @throws MWException
     */
    private function _handleCallback(): void
    {
        try {
            // Try to get an access token using the authorization code grant.
            $accessToken = $this->_provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
        } catch (IdentityProviderException $e) {
            // Failed to get the access token or user details.
            throw new MWException('Retrieving access token failed', 0, $e);
        }
        try {
            /** @var EveOnlineSSOResourceOwner $resourceOwner */
            $resourceOwner = $this->_provider->getResourceOwner($accessToken);
        } catch (Exception $e) {
            throw new MWException('Unable to retrieve character information. Please try again later', 0, $e);
        }
        $request = $this->getRequest();
        $request->getSession()->persist();
        $olduser = $request->getSession()->getUser();
        if ($olduser->isRegistered()) {
            $olduser->doLogout();
        }
        $userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
        $user = $this->_userHandling($resourceOwner);
        $persist = $userOptionsManager->getOption($user, 'mwevesso-persist');
        $user->setCookies(null, null, boolval($persist));
        $title = null;
        $request->getSession()->persist();
        if ($request->getSession()->exists('returnto')) {
            $title = Title::newFromText(
                strval($request->getSession()->get('returnto'))
            );
            $request->getSession()->remove('returnto');
            $request->getSession()->save();
        }
        if (!$title instanceof Title || 0 > $title->getArticleID()) {
            $title = Title::newMainPage();
        }
        $this->getOutput()->redirect($title->getFullURL());
    }

    /**
     * Handles the default case
     *
     * @return void
     */
    private function _default(): void
    {
        $this->getOutput()->setPagetitle(
            wfMessage('mwevesso-login-header', self::OAUTH_SERVISE_NAME)->text()
        );
        $user = RequestContext::getMain()->getUser();
        if (!$user->isRegistered()) {
            $this->getOutput()->addWikiMsg(
                'mwevesso-you-can-login-to-this-wiki-with-oauth2',
                self::OAUTH_SERVISE_NAME
            );
            $this->getOutput()->addWikiMsg(
                'mwevesso-login-with-oauth2',
                $this->getPagetitle('redirect')->getPrefixedURL(),
                self::OAUTH_SERVISE_NAME
            );
        } else {
            $userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
            if (in_array('sysop', $userGroupManager->getUserGroups($user))) {
                global $wgOAuth2Client;
                $config = $wgOAuth2Client;
                if (is_array($config)) {
                    $config['client'] = [
                        'id' => '*******',
                        'secret' => '*******'
                    ];
                }
                $this->getOutput()->addWikiMsg(
                    'mwevesso-admin-callback-url-label'
                );
                $this->getOutput()->addWikiTextAsInterface(
                    '<syntaxhighlight>' .
                    $this->_callbackUrl .
                    '</syntaxhighlight>'
                );
                $this->getOutput()->addWikiMsg(
                    'mwevesso-admin-show-wgoauth2client-label'
                );
                $this->getOutput()->addWikiTextAsInterface(
                    '<syntaxhighlight lang="php">' .
                    '$wgOAuth2Client = ' .
                    var_export($config, true) .
                    '</syntaxhighlight>'
                );
            } else {
                $this->getOutput()->addWikiMsg('mwevesso-youre-already-loggedin');
            }
        }
    }

    /**
     * User handling
     *
     * @param EveOnlineSSOResourceOwner $resourceOwner
     *
     * @return User The user
     * @throws MWException
     */
    protected function _userHandling(EveOnlineSSOResourceOwner $resourceOwner): User
    {
        $characterName = $resourceOwner->getCharacterName();
        if (is_null($characterName)) {
            throw new MWException('Character name is null');
        }
        $allEmpty = empty($this->_allowedAllianceIds)
            && empty($this->_allowedCorporationIds)
            && empty($this->_allowedCharacterIds);
        if (!$allEmpty
            && !in_array($resourceOwner->getAllianceId(), $this->_allowedAllianceIds)
            && !in_array($resourceOwner->getCorporationId(), $this->_allowedCorporationIds)
            && !in_array($resourceOwner->getCharacterID(), $this->_allowedCharacterIds)
        ) {
            throw new MWException(
                'The character that you authenticated (' . $characterName . ') is not authorize to view this wiki'
            );
        }
        $user = User::newFromName($characterName, 'creatable');
        if (!$user) {
            throw new MWException(
                'Could not create user with EVE Character Name as username:' . $characterName
            );
        }
        $user->load();
        if (!($user instanceof User && $user->getId())) { // @phpstan-ignore instanceof.alwaysTrue
            $user->setRealName($characterName);
            $user->addToDatabase();
        }
        $user->setToken();
        // Setup the session
        $request = $this->getRequest();
        $request->getSession()->setSecret("hugs", time());
        $request->getSession()->persist();
        RequestContext::getMain()->setUser($user);
        $user->saveSettings();
        // why are these 2 lines here, they seem to do nothing helpful ?
        $sessionUser = User::newFromSession($request);
        $sessionUser->load();
        return $user;
    }

}
