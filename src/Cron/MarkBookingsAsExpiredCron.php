<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\ModuleModel;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCronJob('minutely')]
class MarkBookingsAsExpiredCron
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): void
    {
        $this->connection->executeStatement(
            'UPDATE tl_resource_booking SET upcoming = 0 WHERE endTime < ? AND upcoming = ?',
            [time(), 1],
            [Types::INTEGER, Types::BOOLEAN],
        );
    }
}
