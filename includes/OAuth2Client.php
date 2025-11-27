<?php
/**
 * OAuth2Client.php
 * Based on TwitterLogin by David Raison, which is based on the guideline published by Dave Challis at http://blogs.ecs.soton.ac.uk/webteam/2010/04/13/254/
 * @license: LGPL (GNU Lesser General Public License) http://www.gnu.org/licenses/lgpl.html
 *
 * @file OAuth2Client.php
 * @ingroup OAuth2Client
 *
 * @author Joost de Keijzer
 * @author Nischay Nahata for Schine GmbH
 *
 * Uses the OAuth2 library https://github.com/thephpleague/oauth2-client
 *
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

use MediaWiki\Auth\AuthManager;
use MediaWiki\Title\Title;

class OAuth2ClientHooks {

	public static function onAuthChangeFormFields($requests, $fieldInfo, &$formDescriptor, $action) {         
        global $wgRequest;                                                                                
        $url = "/index.php?title=Special:OAuth2Client/redirect";                                          
		$ret = $wgRequest->getVal("returnto");                                                            
		
        if(!is_null($ret))                                                                                
        {                                                                                                 
                $url .= "&returnto=".$ret;                                                                
        }                                                                                                 
                                                                                                          
        $formDescriptor["SSOLogin"] = [                                                                   
                "section" => "oauth-login",                                                                  
                "type" => "info",                                                                         
                "default"=>'<div style="text-align: center"><a class = "btn_mwevesso_login" href="'.$url.'">Log in with Eve Online</a></div>',
                "raw"=>true                                                                               
        ];                                                                                                
	}  

	public static function onSecuritySensitiveOperationStatus(&$status, $operation, $session, $timeSinceAuth){  
        if($operation !== "ChangeEmail")                                                                    
        {                                                                                                   
                return;                                                                                     
        }                                                                                                   
                                                                                                            
        $time = time();                                                                                     
        $login = $session->getSecret("hugs", 0);                                                            
        $delta = abs($time - $login);                                                                       
                                                                                                            
        if($delta <300)                                                                                     
        {                                                                                                   
          $status =  AuthManager::SEC_OK;                                                                   
          return;                                                                                           
        }                                                                                                   
        $status =  AuthManager::SEC_REAUTH;                                                                 
	}                                                                                                           

	public static function onGetPreferences($user, &$preferences){          
		$preferences['oauth-persist'] = [                               
				'type'=>'toggle',                                       
				'label-message' => 'oauth-persist',                     
				'section'=> 'misc'                                      
		];                                                              																
	}                                                                       

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $sktemplate, array &$links) {

		global $wgOAuth2Client, $wgRequest;
    
        $user = RequestContext::getMain()->getUser();
		if( $user->isRegistered() ) return true;

		$page = Title::newFromURL( $wgRequest->getVal( 'title', '' ) );

		$inExt = ( null == $page || ('OAuth2Client' == substr( $page->getText(), 0, 12) ) || strstr($page->getText(), 'Logout') );
		$links['user-menu']['anon_oauth_login'] = array(
            'text' => 'LOG IN with EVE Online',
			'class' => 'btn_mwevesso_login',
			'active' => false
		);
		if( $inExt ) {
			$links['user-menu']['anon_oauth_login']['href'] = Skin::makeSpecialUrlSubpage( 'OAuth2Client', 'redirect' );
		} else {
			$links['user-menu']['anon_oauth_login']['href'] = Skin::makeSpecialUrlSubpage(
				'OAuth2Client',
				'redirect',
				wfArrayToCGI( array( 'returnto' => $page ) )
			);
		}

    // Remove default login links
    unset($links['user-menu']['login']);
    unset($links['user-menu']['anonlogin']);

    // Remove account creation link
    unset($links['user-menu']['createaccount']);

    return true;
	}

}
