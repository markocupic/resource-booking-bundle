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

namespace Markocupic\ResourceBookingBundle\Tests\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\AjaxControllerRegistry;
use Markocupic\ResourceBookingBundle\AjaxController\ApplyFilterController;
use Markocupic\ResourceBookingBundle\AjaxController\BookingController;
use Markocupic\ResourceBookingBundle\AjaxController\BookingFormValidationController;
use Markocupic\ResourceBookingBundle\AjaxController\CancelController;
use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Exception\UnknownAjaxActionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

class AjaxControllerRegistryTest extends TestCase
{
    public function testReturnsTheControllerOfAnAction(): void
    {
        $controller = $this->createStub(ControllerInterface::class);
        $registry = new AjaxControllerRegistry(new ServiceLocator(['refreshDataRequest' => static fn () => $controller]));

        $this->assertTrue($registry->has('refreshDataRequest'));
        $this->assertSame($controller, $registry->get('refreshDataRequest'));
    }

    public function testThrowsForAnUnknownAction(): void
    {
        $registry = new AjaxControllerRegistry(new ServiceLocator([]));

        $this->assertFalse($registry->has('fooRequest'));
        $this->assertFalse($registry->has(''));

        $this->expectException(UnknownAjaxActionException::class);

        $registry->get('fooRequest');
    }

    public function testThrowsIfTheServiceIsNoAjaxController(): void
    {
        $registry = new AjaxControllerRegistry(new ServiceLocator(['fooRequest' => static fn () => new \stdClass()]));

        $this->expectException(\LogicException::class);

        $registry->get('fooRequest');
    }

    /**
     * The actions must match the actions sent by the front end (assets/app.js).
     *
     * @param class-string $class
     */
    #[DataProvider('provideControllers')]
    public function testTheControllersAreRegisteredForTheActionsOfTheFrontEnd(string $class, string $action): void
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(AsAjaxController::class);

        $this->assertCount(1, $attributes);
        $this->assertSame($action, $attributes[0]->newInstance()->action);
        $this->assertTrue(is_subclass_of($class, ControllerInterface::class));
    }

    public static function provideControllers(): iterable
    {
        yield [RefreshDataController::class, 'refreshDataRequest'];
        yield [ApplyFilterController::class, 'applyFilterRequest'];
        yield [BookingFormValidationController::class, 'bookingFormValidationRequest'];
        yield [BookingController::class, 'bookingRequest'];
        yield [CancelController::class, 'cancelBookingRequest'];
    }
}
