## About UBIS UI

The UI service represents all UBIS control and data for operators 

## Function Set

Default login
usr: user@example.com
pwd: geheim

## Requirements

- PHP 7.4.x
	- extension=pdo_pgsql
	- extension=pdo_sqlite
	- extension=php_gd2.dll
	- extension=php_curl.dll
	- extension=php_mbstring.dll
	- extension=php_openssl.dll
	- extension=php_pdo_mysql.dll
	- extension=php_pdo_sqlite.dll
	- extension=php_sockets.dll
	- extension=php_fileinfo.dll
- PostgreSQL >= 11
- Laravel 7.1
- Composer >= 1.10


## Installation

- checkout to target
- make sure *storage/* and *bootstrap/cache/* is writable by webserver
- do a *composer update* in project folder
- call *npm install* in project folder
- call *npm run development* in project folder
- modify/create *.env* file (see sample at env.example)
- run *php artisan key:generate* to generate new application keys 
- execute *php artisan migrate:fresh* to setup DB tables and 
- execute *php artisan db:seed* to seed DB with base data
- check welcome screen 


## Configuration

- setup on .env file ERP_SERVICE_BASE_URL
- setup on .env file PC_SERVICE_BASE_URL
- setup on .env file PIS_SERVICE_BASE_URL
- setup on .env file MIX_PRODUCTS_SEARCH_PAGE_URL


## Update

- *composer udpdate* to update laravel (if requested)
- *npm install* to rebuild public assets 
- call *npm run development* in project folder
- *php artisan migrate* to update DB (*php artisan migrate:rollback* if you have to rollback)
- execute *php artisan l5:generate* to re-generate API doc
- not necesarry, but recommend:
	- restart FPM with *echo "" | sudo -S service php7.1-fpm reload*
	- *php artisan queue:restart*
	- *php artisan cache:clear*

## for updating translation: when using vue-gettext

!! requires apt-get install gettext !!

referred to https://github.com/Polyconseil/vue-gettext
- check the Makefile
- Annotating strings: to make a Vue.js app translatable, you have to annotate the strings you want to translate in your JavaScript code and/or templates.
- to update .po files, run "make makemessages"
  check resources/js/locale
- edit .po files for every language
- to regenerate translation.json file, run "make clean", run "make translations"
  check resources/js/translations.json
  
  
## for updating translation: when using i18n

- refer to vue-i18n-locales.generated.js file in resources/js directory
- execute *php artisan vue-i18n:generate* to overwrite the vue-i18n-locales.generated.js
  - this command refers to the php files in lang directory
  
**[Online description laravel deploy](https://laravel.com/docs/7.x/deployment)**

**[Online description setting up laravel/nginx](https://laraveldaily.com/how-to-deploy-laravel-projects-to-live-server-the-ultimate-guide/)**
