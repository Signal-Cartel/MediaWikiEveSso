<?php

declare(strict_types=1);

namespace MediaWiki\Extension\MWEVESSO;

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

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\Hook\SecuritySensitiveOperationStatusHook;
use MediaWiki\Context\RequestContext;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\Title\Title;

class OAuth2ClientHooks implements
    AuthChangeFormFieldsHook,
    GetPreferencesHook,
    SecuritySensitiveOperationStatusHook,
    SkinTemplateNavigation__UniversalHook
{
    /**
     * The request context
     *
     * @var RequestContext|null
     */
    private ?RequestContext $_context = null;

    /**
     * Get context
     *
     * @return RequestContext
     */
    private function _getContext(): RequestContext
    {
        if (isset($this->_context)) {
            return $this->_context;
        }
        $this->_context = RequestContext::getMain();
        return $this->_context;
    }

    /**
     * Get the request
     *
     * @return WebRequest
     */
    private function _getRequest(): WebRequest
    {
        return $this->_getContext()->getRequest();
    }

    /**
     * Get the output
     *
     * @return OutputPage
     */
    private function _getOutput(): OutputPage
    {
        return $this->_getContext()->getOutput();
    }

    /**
     * Attach javascript and CSS
     *
     * @return void
     */
    private function _addModules(): void
    {
        global $wgOAuth2Client;
        $modules = [];
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        if (!empty($wgOAuth2Client['configuration']['theme'])
            && $wgOAuth2Client['configuration']['theme'] == 'white'
        ) {
            $modules[] = 'mw.evesso.login-white';
        } else {
            $modules[] = 'mw.evesso.login-black';
        }
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        if (!empty($wgOAuth2Client['configuration']['modal'])) {
            $modules[] = 'mw.evesso.modal';
        }
        $this->_getOutput()->addModules($modules);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthChangeFormFields(
        $requests,
        $fieldInfo,
        &$formDescriptor,
        $action
    ) {
        $ret = $this->_getRequest()->getVal("returnto");
        $url = Helper::getRedirecUrl($ret);
        $formDescriptor["SSOLogin"] = [
            "section" => "mwevesso-oauth-login",
            "type" => "info",
            "default" => '<div class="mwevesso-login-wrap"><a href="' . $url . '">' . Helper::t('mwevesso-log-in-with-eve-online'). '</a></div>',
            "raw" => true
        ];
        $this->_addModules();
    }

    /**
     * {@inheritdoc}
     */
    public function onSecuritySensitiveOperationStatus(
        &$status,
        $operation,
        $session,
        $timeSinceAuth
    ) {
        if ($operation !== "ChangeEmail") {
            return true;
        }
        $time = time();
        $login = intval($session->getSecret("hugs", 0));
        $delta = abs($time - $login);
        if ($delta < 300) {
            $status = AuthManager::SEC_OK;
            return;
        }
        $status = AuthManager::SEC_REAUTH;
    }

    /**
     * {@inheritdoc}
     */
    public function onGetPreferences($user, &$preferences)
    {
        $preferences['mwevesso-persist'] = [
            'type' => 'toggle',
            'label-message' => 'mwevesso-persist',
            'section' => 'misc'
        ];
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function onSkinTemplateNavigation__Universal($sktemplate, &$links): void
    {
        global $wgOAuth2Client;
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        if (empty($wgOAuth2Client['configuration']['replace_user_menu'])) {
            return;
        }
        $user = $this->_getContext()->getUser();
        if ($user->isRegistered()) {
            return;
        }
        $title = $this->_getRequest()->getVal('title', '');
        if (is_null($title)) {
            $title = '';
        }
        $page = Title::newFromURL($title);
        if (is_null($page) || $page->isSpecialPage()) {
            $returnto = null;
        } else {
            $returnto = (string) $page;
        }
        /** @var array $links */
        $links['user-menu'] = [ // @phpstan-ignore missingType.iterableValue
            'mwevesso_login' => [
                'single-id' => 'pt-mwevesso-login',
                'text' => Helper::t('mwevesso-log-in-with-eve-online'),
                'class' => 'mwevesso-login-wrap',
                'active' => false,
                'href' => Helper::getRedirecUrl($returnto)
            ]
        ];
        $this->_addModules();
    }

}
