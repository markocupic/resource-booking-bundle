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

namespace Markocupic\ResourceBookingBundle\Tests\DataContainer;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\System;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\DataContainer\Module;

class ModuleTest extends ContaoTestCase
{
    public function testGetAppConfigurationsReturnsTheConfigKeys(): void
    {
        $module = new Module(
            $this->createContaoFrameworkMock(),
            $this->createMock(Connection::class),
            ['default' => [], 'app_b' => []],
        );

        $this->assertSame(['default', 'app_b'], $module->getAppConfigurations());
    }

    public function testGetTlMemberFieldsReturnsTheConfiguredFieldsThatExist(): void
    {
        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'firstname', 'lastname', 'password', 'email', 'city', 'secret']),
            $this->createMock(Connection::class),
            [],
            // "company" does not exist, "secret" is never permitted
            ['lastname', 'firstname', 'company', 'city', 'secret'],
        );

        $this->assertSame(['lastname', 'firstname', 'city'], $module->getTlMemberFields());
    }

    public function testGetTlMemberFieldsUsesTheDefaultFields(): void
    {
        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'firstname', 'lastname', 'password', 'email', 'company', 'city', 'phone']),
            $this->createMock(Connection::class),
        );

        $this->assertSame(['firstname', 'lastname', 'company', 'city', 'phone'], $module->getTlMemberFields());
    }

    public function testGetTlResourceBookingFieldsReturnsTheConfiguredFieldsThatExist(): void
    {
        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'pid', 'member', 'title', 'description', 'bookingUuid']),
            $this->createMock(Connection::class),
        );

        // Default: title and description, not member or bookingUuid
        $this->assertSame(['title', 'description'], $module->getTlResourceBookingFields());

        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'pid', 'member', 'title', 'description']),
            $this->createMock(Connection::class),
            [],
            [],
            ['description', 'unknown'],
        );

        $this->assertSame(['description'], $module->getTlResourceBookingFields());
    }

    /**
     * @param list<string> $fieldNames
     */
    private function frameworkWithFieldNames(array $fieldNames): ContaoFramework
    {
        // A tiny stand-in for Database::getInstance() that only needs getFieldNames().
        $databaseInstance = new class($fieldNames) {
            /**
             * @param list<string> $fieldNames
             */
            public function __construct(private readonly array $fieldNames)
            {
            }

            public function getFieldNames(string $table): array
            {
                return $this->fieldNames;
            }
        };

        $databaseAdapter = $this->createAdapterMock(['getInstance']);
        $databaseAdapter
            ->method('getInstance')
            ->willReturn($databaseInstance)
        ;

        $systemAdapter = $this->createAdapterMock(['loadLanguageFile']);

        return $this->createContaoFrameworkMock([
            Database::class => $databaseAdapter,
            System::class => $systemAdapter,
        ]);
    }
}
