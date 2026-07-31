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
            $this->mockContaoFramework(),
            $this->createMock(Connection::class),
            ['app_a' => [], 'app_b' => []],
        );

        $this->assertSame(['app_a', 'app_b'], $module->getAppConfigurations());
    }

    public function testGetTlMemberFieldsExcludesIdAndPassword(): void
    {
        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'firstname', 'lastname', 'password', 'email']),
            $this->createMock(Connection::class),
            [],
        );

        $this->assertSame(['firstname', 'lastname', 'email'], $module->getTlMemberFields());
    }

    public function testGetTlResourceBookingFieldsReturnsAllFields(): void
    {
        $module = new Module(
            $this->frameworkWithFieldNames(['id', 'pid', 'startTime', 'endTime']),
            $this->createMock(Connection::class),
            [],
        );

        $this->assertSame(['id', 'pid', 'startTime', 'endTime'], $module->getTlResourceBookingFields());
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

        $databaseAdapter = $this->mockAdapter(['getInstance']);
        $databaseAdapter
            ->method('getInstance')
            ->willReturn($databaseInstance)
        ;

        $systemAdapter = $this->mockAdapter(['loadLanguageFile']);

        return $this->mockContaoFramework([
            Database::class => $databaseAdapter,
            System::class => $systemAdapter,
        ]);
    }
}
