# MediaWiki OAuth2 Client

Alpha version of an OAuth2 client that will allow users to create accounts
and log in to MediaWiki using [EVE Online's SSO](https://support.eveonline.com/hc/articles/205381192-Single-Sign-On-SSO) service.

This is a fork of [Signal-Cartel/MediaWikiEveSso](https://github.com/Signal-Cartel/MediaWikiEveSso)

Tested with MediaWiki 1.43, did not test lower versions

## Installation

Install composer. See [documentation](https://getcomposer.org/download/).

Change to the directory where MediaWiki is installed:

```bash
cd /path/to/mediawiki
```

Clone this repo into a `extensions/MW-EVE-SSO`:

```bash
git clone --depth 1 --branch "master" --single-branch \
  "https://github.com/6RUN0/MediaWikiEveSso.git" \
  "extensions/MW-EVE-SSO"
```

Move the `composer.local.json-sample` file to `composer.local.json`

```bash
mv composer.local.json-sample composer.local.json
```

Finally, run composer install.

```bash
composer install
```

## Usage

Add the following line to your LocalSettings.php file.

```php
wfLoadExtension('MW-EVE-SSO');
```

Prevent new user registration

```php
$wgGroupPermissions['*']['createaccount'] = false;
```

Required settings to be added to `LocalSettings.php`
You can get a client ID and Secret by registering an SSO Application for your wiki on the [EVE Developers](https://developers.eveonline.com/) site

```php
// The client ID assigned to you by the provider
$wgOAuth2Client['client']['id']     = '';
// The client secret assigned to you by the provider
$wgOAuth2Client['client']['secret'] = '';
```

The **Redirect URI** for your wiki should be:

```code
http://your.wiki.domain/path/to/wiki/Special:OAuth2Client/callback
```

Configure which EVE characters are allowed to log in

```php
// All members of these alliances will be able to log in
$wgOAuth2Client['configuration']['allowed_alliance_ids'] = [];
// Specify specific characters here
$wgOAuth2Client['configuration']['allowed_character_ids'] = [];
// All members of these corporations will be abe to log in
$wgOAuth2Client['configuration']['allowed_corporation_ids'] = [];
```

You can replace the login menu with a login button via EVE SSO

```php
$wgOAuth2Client['configuration']['replace_user_menu'] = true;
```

You can choose the theme of the login button through EVE SSO black or white (by default `black`)

```php
$wgOAuth2Client['configuration']['theme'] = 'white';
// or
//$wgOAuth2Client['configuration']['theme'] = 'black';
```

You can open the login link in a modal window. But I don't recommend it

```php
$wgOAuth2Client['configuration']['modal'] = true;
```

## See also

- [esi-docs/SSO](https://docs.esi.evetech.net/docs/sso/)
- [support.eveonline.com/Single Sign On (SSO)](https://support.eveonline.com/hc/articles/205381192-Single-Sign-On-SSO)
- [Yeeshani/MW-EVE-SSO](https://github.com/Yeeshani/MW-EVE-SSO)
- [mostertb/MW-EVE-SSO](https://github.com/mostertb/MW-EVE-SSO)
- [Signal-Cartel/MediaWikiEveSso](https://github.com/Signal-Cartel/MediaWikiEveSso)
- [windstep/MediaWikiEveSso](https://github.com/windstep/MediaWikiEveSso)
- [Schine/MW-OAuth2Client](https://github.com/Schine/MW-OAuth2Client)

## License

LGPL (GNU Lesser General Public License) <http://www.gnu.org/licenses/lgpl.html>
