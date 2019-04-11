
# Translations Updater

* Contributors: [Andy Fragen](https://github.com/afragen)
* Tags: plugins, themes, edd software licensing, language pack, updater
* Requires at least: 4.6
* Requires PHP: 5.4
* Donate link: <http://thefragens.com/translations-updater-donate>
* License: MIT
* License URI: <http://www.opensource.org/licenses/MIT>

## Description

This framework allows for decoupled language pack updates for your WordPress plugins or themes that are hosted on public repositories in GitHub, Bitbucket, GitLab, or Gitea.

 The URI should point to a repository that contains the translations files. Refer to [GitHub Updater Translations](https://github.com/afragen/github-updater-translations) as an example. It is created using the [Language Pack Maker](https://github.com/afragen/language-pack-maker). The repo **must** be a public repo.

## Usage

Install via Composer: `composer require afragen/translations-updater:dev-master`

**Prior to release use the following command**
`composer require afragen/translations-updater:dev-<branch>` currently `dev-master`

Add `require_once __DIR__ . '/vendor/autoload.php';` to the main plugin file or theme's functions.php file.

A configuration array with the following format is needed. All array elements are required.

```php
add_action( 'admin_init', function() {
	$config = [
		'git'       => '(github|bitbucket|gitlab|gitea)',
		'type'      => '(plugin|theme)',
		'slug'      => 'my-repo-slug',
		'version'   => 'my-repo-version', // Current version of plugin|theme.
		'languages' => 'https://my-path-to/language-packs',
	];

	( new \Fragen\Translations_Updater\Init() )->run( $config );
} );
```

If you wish to delete the data stored in the options table associated with this framework you will need to issue the following command.

```php
( new \Fragen\Translations_Updater\Init() )->delete_cached_data();
```

## EDD Software Licensing Usage

If using the EDD Software Licensing Updater plugin, this framework is already installed.

You will need to add three key/value pairs to your EDD SL Add-on setup array similar to the following,

```php
'type'      => '(plugin|theme)',
'git'       => 'github',
'languages' => 'https://github.com/<USER>/my-language-pack',
```

Please refer to the EDD SL Updater plugin README and samples for detailed instructions.
