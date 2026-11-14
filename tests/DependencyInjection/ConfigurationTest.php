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

namespace Markocupic\ResourceBookingBundle\Tests\DependencyInjection;

use Markocupic\ResourceBookingBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDisplayableFieldsHaveSafeDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame(['firstname', 'lastname', 'company', 'city', 'phone'], $config['displayable_member_fields']);
        $this->assertSame(['title', 'description'], $config['displayable_booking_fields']);
    }

    public function testDisplayableFieldsCanBeConfigured(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['displayable_member_fields' => ['firstname', 'lastname'], 'displayable_booking_fields' => ['description']],
        ]);

        $this->assertSame(['firstname', 'lastname'], $config['displayable_member_fields']);
        $this->assertSame(['description'], $config['displayable_booking_fields']);
    }

    public function testSecretMemberFieldsCannotBeConfigured(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            ['displayable_member_fields' => ['firstname', 'password']],
        ]);
    }
}
