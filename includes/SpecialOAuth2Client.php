<?php
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

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class SpecialOAuth2Client extends SpecialPage {

	private $_provider;

	/**
	 * Required settings in global $wgOAuth2Client
	 *
	 * $wgOAuth2Client['client']['id']
	 * $wgOAuth2Client['client']['secret']
     * $wgOAuth2Client['configuration']['redirect_uri']
     *
     * $wgOAuth2Client['configuration']['allowed_corporation_ids']
     * $wgOAuth2Client['configuration']['allowed_character_ids']
	 */
	public function __construct() {

		parent::__construct('OAuth2Client');
		global $wgOAuth2Client;

		require __DIR__ . '/../vendors/oauth2-client/vendor/autoload.php';

		$this->_provider = new EveOnlineSSOProvider([
            'clientId'                => $wgOAuth2Client['client']['id'],    // The client ID assigned to you by the provider
            'clientSecret'            => $wgOAuth2Client['client']['secret'],   // The client password assigned to you by the provider
            'redirectUri'             => $wgOAuth2Client['configuration']['redirect_uri']
        ]);
	}

	// default method being called by a specialpage
	public function execute( $parameter ){
		$this->setHeaders();
		switch($parameter){
			case 'redirect':
				try
				{
					$this->_redirect();
				}
				catch (\Exception $e){
					$this->_showError($e->getMessage());
				}
			break;
			case 'callback':
				try
				{
					$this->_handleCallback();
				}
				catch (\Exception $e){
					$this->_showError($e->getMessage());
				}
			break;
			default:
				$this->_default();
			break;
		}

	}
	private function _showError($error_msg)
	{
		global $wgOut, $wgUser;
		$service_name = 'EVE Online SSO';

		$wgOut->setPagetitle( wfMessage( 'oauth2client-login-header', $service_name)->text() );
		$wgOut->addWikiMsg( 'oauth2client-error', $error_msg );
		
	}

	private function _redirect() {

		global $wgRequest, $wgOut;
		$wgRequest->getSession()->persist();
		$wgRequest->getSession()->set('returnto', $wgRequest->getVal( 'returnto' ));

		// Fetch the authorization URL from the provider; this returns the
		// urlAuthorize option and generates and applies any necessary parameters
		// (e.g. state).
		$authorizationUrl = $this->_provider->getAuthorizationUrl();

		// Get the state generated for you and store it to the session.
		$wgRequest->getSession()->set('oauth2state', $this->_provider->getState());
		$wgRequest->getSession()->save();

		// Redirect the user to the authorization URL.
		$wgOut->redirect( $authorizationUrl );
	}

    /**
     * @return bool
     * @throws MWException
     */
    private function _handleCallback(){
		try {

			// Try to get an access token using the authorization code grant.
			$accessToken = $this->_provider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);
		} catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
			// Failed to get the access token or user details.
            throw new MWException('Retrieving access token failed',0, $e);
		}

		try{
            /** @var EveOnlineSSOResourceOwner $resourceOwner */
            $resourceOwner = $this->_provider->getResourceOwner($accessToken);
        } catch (\Exception $e){
            throw new MWException('Unable to retrieve character information. Please try again later',0, $e);
		}
		global $wgOut, $wgRequest;

		$wgRequest->getSession()->persist();
		$olduser = $wgRequest->getSession()->getUser();
		if($olduser->isRegistered())
		{
				$olduser->doLogout();
		}

	    	$userOptionsManager = MediaWikiServices::getInstance()->getUserOptionsManager();
		$user = $this->_userHandling( $resourceOwner );
		$persist = $userOptionsManager->getOption($user, 'oauth-persist');
		$user->setCookies(null,null,$persist);

		$title = null;
		$wgRequest->getSession()->persist();
		if( $wgRequest->getSession()->exists('returnto') ) {
			$title = Title::newFromText( $wgRequest->getSession()->get('returnto') );
			$wgRequest->getSession()->remove('returnto');
			$wgRequest->getSession()->save();
		}

		if( !$title instanceof Title || 0 > $title->getArticleID ) {
			$title = Title::newMainPage();
		}
		$wgOut->redirect( $title->getFullURL() );
		return true;
	}

	private function _default(){
		global $wgOut;
		$service_name = 'EVE Online SSO';

		$wgOut->setPagetitle( wfMessage( 'oauth2client-login-header', $service_name)->text() );

		$user = RequestContext::getMain()->getUser();
		if ( !$user->isRegistered() ) {
			$wgOut->addWikiMsg( 'oauth2client-you-can-login-to-this-wiki-with-oauth2', $service_name );
			$wgOut->addWikiMsg( 'oauth2client-login-with-oauth2', $this->getPagetitle( 'redirect' )->getPrefixedURL(), $service_name );
		} else {
			$wgOut->addWikiMsg( 'oauth2client-youre-already-loggedin' );
		}
		return true;
	}

    /**
     * @param EveOnlineSSOResourceOwner $resourceOwner
     *
     * @return bool|User
     * @throws MWException
     */
    protected function _userHandling( EveOnlineSSOResourceOwner $resourceOwner ) {
		global $wgOAuth2Client, $wgAuth, $wgRequest;

		$allowedAllianceIds = [];
		if(isset( $wgOAuth2Client['configuration']['allowed_alliance_ids'] ) && 0 < count( $wgOAuth2Client['configuration']['allowed_alliance_ids'] )){
		    $allowedAllianceIds = $wgOAuth2Client['configuration']['allowed_alliance_ids'];
        }

		$allowedCorporationIds = [];
		if(isset( $wgOAuth2Client['configuration']['allowed_corporation_ids'] ) && 0 < count( $wgOAuth2Client['configuration']['allowed_corporation_ids'] )){
		    $allowedCorporationIds = $wgOAuth2Client['configuration']['allowed_corporation_ids'];
        }
        $allowedCharacterIds = [];
        if(isset( $wgOAuth2Client['configuration']['allowed_character_ids'] ) && 0 < count( $wgOAuth2Client['configuration']['allowed_character_ids'] )){
            $allowedCharacterIds = $wgOAuth2Client['configuration']['allowed_character_ids'];
        }

        if(!in_array($resourceOwner->getAllianceId(), $allowedAllianceIds) && !in_array($resourceOwner->getCorporationId(), $allowedCorporationIds) && !in_array($resourceOwner->getCharacterID(), $allowedCharacterIds) ){
            throw new MWException('The character that you authenticated ('.$resourceOwner->getCharacterName().
                ') is not authorize to view this wiki');
        }

		$user = User::newFromName($resourceOwner->getCharacterName(), 'creatable');
		if (!$user) {
			throw new MWException('Could not create user with EVE Character Name as username:' . $resourceOwner->getCharacterName());
		}

		$user->load();
		if ( !( $user instanceof User && $user->getId() ) ) {
            $user->setRealName($resourceOwner->getCharacterName());
			$user->addToDatabase();
		}
		$user->setToken();

		// Setup the session
		$wgRequest->getSession()->setSecret("hugs",time());
		$wgRequest->getSession()->persist();
		$this->getContext()->setUser( $user );
		$user->saveSettings();
		RequestContext::getMain()->setUser( $user );
		
		// why are these 2 lines here, they seem to do nothing helpful ?
		$sessionUser = User::newFromSession($this->getRequest());
		$sessionUser->load();

		return $user;
	}

}

