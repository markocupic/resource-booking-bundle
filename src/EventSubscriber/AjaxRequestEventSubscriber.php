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

namespace Markocupic\ResourceBookingBundle\EventSubscriber;

use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class AjaxRequestEventSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 1000;

    private array $services = [];

    private array $controllers = [];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::class => ['onXmlHttpRequest', self::PRIORITY],
        ];
    }

    public function add(ControllerInterface $controller, string $alias, string $id): void
    {
        $this->controllers[$alias] = $controller;
        $this->services[$alias] = $id;
    }

    /**
     * @throws \Exception
     */
    public function onXmlHttpRequest(AjaxRequestEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $action = $request->request->get('action', '');
        $alias = str_replace('Request', '', $action);

        if (\array_key_exists($alias, $this->controllers)) {
            $controller = $this->get($alias);

            $ajaxResponse = $controller->generateResponse($event->getAjaxResponse());
            $event->setAjaxResponse($ajaxResponse);
        } else {
            throw new \Exception(\sprintf('Could not find Controller for action "%s".', $action));
        }
    }

    /**
     * Get a resource by alias.
     */
    public function get(string $alias): ControllerInterface
    {
        if (!\array_key_exists($alias, $this->controllers)) {
            throw new \LogicException(\sprintf('Resource with alias "%s" not found.', $alias));
        }

        return $this->controllers[$alias];
    }
}
