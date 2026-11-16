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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Exception\UnknownAjaxActionException;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

/**
 * All Ajax controllers of the week calendar, indexed by their action
 * (see AsAjaxController). The controllers are only instantiated when needed.
 */
class AjaxControllerRegistry
{
    public function __construct(
        #[AutowireLocator(AsAjaxController::TAG, indexAttribute: 'action')]
        private readonly ContainerInterface $controllers,
    ) {
    }

    public function has(string $action): bool
    {
        return '' !== $action && $this->controllers->has($action);
    }

    /**
     * @throws UnknownAjaxActionException
     */
    public function get(string $action): ControllerInterface
    {
        if (!$this->has($action)) {
            throw new UnknownAjaxActionException(\sprintf('No Ajax controller is registered for the action "%s".', $action));
        }

        $controller = $this->controllers->get($action);

        if (!$controller instanceof ControllerInterface) {
            throw new \LogicException(\sprintf('The Ajax controller "%s" must implement %s.', $controller::class, ControllerInterface::class));
        }

        return $controller;
    }
}
