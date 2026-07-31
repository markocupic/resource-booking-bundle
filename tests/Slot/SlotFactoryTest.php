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

namespace Markocupic\ResourceBookingBundle\Tests\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Util\Utils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SlotFactoryTest extends TestCase
{
    public function testGetThrowsForAnUnknownMode(): void
    {
        $factory = new SlotFactory(
            $this->createMock(ContaoFramework::class),
            $this->createMock(TokenStorageInterface::class),
            $this->createMock(Utils::class),
        );

        // The mode is validated before any collaborator is touched.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('unknown-mode');

        $factory->get(
            1,
            'unknown-mode',
            $this->createMock(ResourceBookingResourceModel::class),
            0,
            100,
        );
    }
}
