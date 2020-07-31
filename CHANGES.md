#### [unreleased]
* initial commit
* updated samples
* consolidated strings
* added `afragen/translations-updater` for decoupled language pack updating
* added initialization and integration for `afragen/translations-updater`
* updated samples to include test for class to prevent fatal when updating Updater
* added `wp-dependency.json`, `composer require afragen/wp-dependency-installer`, and install code for samples to automatically install EDD SL Updater
* created `class Init` with universal instantiator function, just add configuration array
* created filter hook `edd_sl_license_form_table` to easily customize the license form
* switch to `site_transient_update_{plugins|themes}` filter
* created Settings page for licenses instead of appending to menus
* added ability to set updater only using `Init->updater( $config )`
* update examples for `afragen/wp-dependency-installer:^3`
* sanitize, escape & ignore
* remove some unnecessary function specific `$cache_key` references, [#1607](https://github.com/easydigitaldownloads/EDD-Software-Licensing/issues/1607)
* add `update-available` to update transient so auto update link displays, WP 5.5
* add plugin data to `$transient->no_update` so auto update link displays
* refactor to use cache
* remove calling custom update meta row
* plugin updating via WP 5.5 auto-updating works, needed to bypass caps check during wp-cron 🤔
