:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/resource-booking-bundle/src --config vendor/markocupic/resource-booking-bundle/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/resource-booking-bundle/tests --config vendor/markocupic/resource-booking-bundle/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/markocupic/resource-booking-bundle/src/Resources/contao --config vendor/markocupic/resource-booking-bundle/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/markocupic/resource-booking-bundle/src/Resources/contao/templates --config vendor/markocupic/resource-booking-bundle/.ecs/config/template.php
::
cd vendor/markocupic/resource-booking-bundle/.ecs./batch/check
