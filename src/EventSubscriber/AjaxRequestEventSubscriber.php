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

    /**
     * @var array<string, ControllerInterface>
     */
    private array $controllers = [];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::class => ['onXmlHttpRequest', self::PRIORITY],
        ];
    }

    public function add(ControllerInterface $controller, string $alias): void
    {
        $this->controllers[$alias] = $controller;
    }

    /**
     * @throws \Exception
     */
    public function onXmlHttpRequest(AjaxRequestEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->isXmlHttpRequest()) {
            return;
        }

        $action = $request->request->get('action', '');
        $alias = str_replace('Request', '', $action);

        $ajaxResponse = $this->get($alias)->generateResponse($request, $event->getAjaxResponse());
        $event->setAjaxResponse($ajaxResponse);
    }

    public function get(string $alias): ControllerInterface
    {
        return $this->controllers[$alias]
            ?? throw new \Exception(\sprintf('Could not find Controller for action "%s".', $alias));
    }
}
