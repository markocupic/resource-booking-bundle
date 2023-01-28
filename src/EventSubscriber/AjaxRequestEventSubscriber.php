<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
    private array $resources = [];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function add(ControllerInterface $resource, string $alias, string $id): void
    {
        $this->resources[$alias] = $resource;
        $this->services[$alias] = $id;
    }

    /**
     * Get a resource by alias.
     */
    public function get(string $alias): ControllerInterface
    {
        if (\array_key_exists($alias, $this->resources)) {
            return $this->resources[$alias];
        }

        throw new \LogicException(sprintf('Resource with alias "%s" not found.', $alias));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::NAME => ['onXmlHttpRequest', self::PRIORITY],
        ];
    }

    /**
     * @throws \Exception
     */
    public function onXmlHttpRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {
            $action = $request->request->get('action', '');
            $alias = str_replace('Request', '', $action);

            if (\array_key_exists($alias, $this->resources)) {
                $controller = $this->get($alias);
                $controller->generateResponse($ajaxRequestEvent);
            } else {
                throw new \Exception(sprintf('Could not find Controller for action "%s".', $action));
            }
        }
    }
}
