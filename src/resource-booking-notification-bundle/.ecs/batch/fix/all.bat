:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/resource-booking-notification-bundle/src --fix --config vendor/markocupic/resource-booking-notification-bundle/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/resource-booking-notification-bundle/tests --fix --config vendor/markocupic/resource-booking-notification-bundle/.ecs/config/tests.php
:: legacy
vendor\bin\ecs check vendor/markocupic/resource-booking-notification-bundle/src/Resources/contao --fix --config vendor/markocupic/resource-booking-notification-bundle/.ecs/config/legacy.php
::
cd vendor/markocupic/resource-booking-notification-bundle/.ecs./batch/fix
