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

namespace Markocupic\ResourceBookingBundle\Migration\UseModernWeekcalendarTemplate;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;

/**
 * The template of the week calendar "mod_resourceBookingWeekcalendar" has been replaced
 * by "frontend_module/resource_booking_weekcalendar". Modules that selected the old
 * template explicitly use the default template again.
 *
 * Custom templates (e.g. mod_resourceBookingWeekcalendar_custom) have to be converted
 * manually to variants in templates/frontend_module/resource_booking_weekcalendar/, see UPGRADE.md.
 */
class UseModernWeekcalendarTemplate extends AbstractMigration
{
    private const OLD_TEMPLATE = 'mod_resourceBookingWeekcalendar';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function getName(): string
    {
        return 'Resource Booking Bundle: Use the default template frontend_module/resource_booking_weekcalendar';
    }

    /**
     * @throws Exception
     */
    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['tl_module']) || !isset($schemaManager->listTableColumns('tl_module')['customtpl'])) {
            return false;
        }

        return false !== $this->connection->fetchOne(
            'SELECT id FROM tl_module WHERE type = ? AND customTpl = ? LIMIT 1',
            [ResourceBookingWeekcalendarController::TYPE, self::OLD_TEMPLATE],
        );
    }

    /**
     * @throws Exception
     */
    public function run(): MigrationResult
    {
        $affectedRows = $this->connection->executeStatement(
            "UPDATE tl_module SET customTpl = '' WHERE type = ? AND customTpl = ?",
            [ResourceBookingWeekcalendarController::TYPE, self::OLD_TEMPLATE],
        );

        return $this->createResult(true, \sprintf('Reset the template of %d week calendar modules to the default template.', $affectedRows));
    }
}
