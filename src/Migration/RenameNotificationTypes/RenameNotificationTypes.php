<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Migration\RenameNotificationTypes;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;

/**
 * Renames the notification types of Notification Center 1:
 *
 *   onbooking -> resource-booking-notification
 *   oncanceling -> resource-booking-cancellation-notification
 */
class RenameNotificationTypes extends AbstractMigration
{
    private const TYPES = [
        'onbooking' => BookingNotificationType::NAME,
        'oncanceling' => CancellationNotificationType::NAME,
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Rename the notification types onbooking/oncanceling';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['tl_nc_notification'])) {
            return false;
        }

        return false !== $this->connection->fetchOne(
            'SELECT id FROM tl_nc_notification WHERE type IN (?, ?)',
            array_keys(self::TYPES),
        );
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $affectedRows = 0;

        foreach (self::TYPES as $oldType => $newType) {
            $affectedRows += (int) $this->connection->update('tl_nc_notification', ['type' => $newType], ['type' => $oldType]);
        }

        return $this->createResult(true, \sprintf('Renamed the type of %d resource booking notifications in tl_nc_notification.', $affectedRows));
    }
}
