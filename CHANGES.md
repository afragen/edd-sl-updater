#### [unreleased]
* initial commit
* update samples
* consolidate strings
* added `afragen/translations-updater` for decoupled language pack updating
* added initialization and integration for `afragen/translations-updater`
* update samples to include test for class to prevent fatal when updating Updater
* add `wp-dependency.json`, `composer require afragen/wp-dependency-installer`, and install code for samples to automatically install EDD SL Updater
* create `class Init` with universal instantiator function, just add configuration array
* created filter hook `edd_sl_license_form_table` to easily customize the license form
