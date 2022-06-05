<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ECSConfig): void {
    $ECSConfig->import(__DIR__.'/../../../../contao/easy-coding-standard/config/default.php');
    $ECSConfig->import(__DIR__.'/set/header_comment_fixer.php');
};
