:: Run easy-coding-standard (ecs) via this batch file inside your IDE e.g. PhpStorm (Windows only)
:: Install inside PhpStorm the  "Batch Script Support" plugin
cd..
cd..
cd..
cd..
cd..
cd..
:: src
vendor\bin\ecs check vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/src --fix --config vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/.ecs/config/default.php
:: tests
vendor\bin\ecs check vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/tests --fix --config vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/.ecs/config/default.php
:: legacy
vendor\bin\ecs check vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/src/Resources/contao --fix --config vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/.ecs/config/legacy.php
:: templates
vendor\bin\ecs check vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/src/Resources/contao/templates --fix --config vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/.ecs/config/template.php
::
cd vendor/<?= $this->vendorname ?>/<?= $this->repositoryname ?>/.ecs./batch/fix
