:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/markocupic/contao-sidebar-navigation/src --fix --config vendor/markocupic/contao-sidebar-navigation/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/markocupic/contao-sidebar-navigation/tests --fix --config vendor/markocupic/contao-sidebar-navigation/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/markocupic/contao-sidebar-navigation/src/Resources/contao --fix --config vendor/markocupic/contao-sidebar-navigation/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/markocupic/contao-sidebar-navigation/src/Resources/contao/templates --fix --config vendor/markocupic/contao-sidebar-navigation/.ecs/config/template.php
::
cd vendor/markocupic/contao-sidebar-navigation/.ecs./batch/fix
