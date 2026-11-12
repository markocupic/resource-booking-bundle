:: Run the unit tests via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
@echo off

:: Change to the Contao installation: <project>\vendor\markocupic\resource-booking-bundle\tools\<tool>\batch -> <project>
cd /d "%~dp0..\..\..\..\..\.."

:: phpunit/phpunit and contao/test-case have to be installed in the Contao installation (require-dev).
php vendor/bin/phpunit -c vendor/markocupic/resource-booking-bundle/tools/phpunit/phpunit.xml.dist
