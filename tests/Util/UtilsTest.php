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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\HttpFoundation\RequestStack;

class UtilsTest extends ContaoTestCase
{
    private const APP_CONFIGS = [
        'default' => ['begin_week' => 'monday', 'permitted_form_fields' => []],
        'sunday_app' => ['begin_week' => 'sunday', 'permitted_form_fields' => ['phone', 'itemsBooked']],
    ];

    public function testGetAppConfigByNameReturnsTheSelectedApp(): void
    {
        $this->assertSame(
            [
                'beginWeek' => 'sunday',
                // The base fields are always permitted, duplicates are removed.
                'permittedFormFields' => ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp', 'phone'],
            ],
            $this->getUtils()->getAppConfigByName('sunday_app'),
        );
    }

    public function testGetAppConfigByNameFallsBackToTheDefaultApp(): void
    {
        $this->assertSame('monday', $this->getUtils()->getAppConfigByName('unknown')['beginWeek']);
        $this->assertSame('monday', $this->getUtils()->getAppConfigByName(null)['beginWeek']);
    }

    public function testGetAppConfigByNameFallsBackToTheDefaultsWithoutApps(): void
    {
        $utils = new Utils($this->createContaoFrameworkMock(), new RequestStack(), 'rbb_bag', []);

        $this->assertSame(
            ['beginWeek' => 'monday', 'permittedFormFields' => ['bookingDescription', 'itemsBooked', 'bookingRepeatStopWeekTstamp']],
            $utils->getAppConfigByName('default'),
        );
    }

    public function testGetAppConfigWithoutSessionUsesTheDefaultApp(): void
    {
        $this->assertSame('monday', $this->getUtils()->getAppConfig()['beginWeek']);
    }

    private function getUtils(): Utils
    {
        return new Utils($this->createContaoFrameworkMock(), new RequestStack(), 'rbb_bag', self::APP_CONFIGS);
    }
}
