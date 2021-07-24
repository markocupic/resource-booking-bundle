<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\EventSubscriber;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AjaxRequestEventSubscriber.
 */
final class AjaxRequestEventSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 1000;

    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private $services = [];
    private $resources = [];

    /**
     * AjaxRequestEventSubscriber constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
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
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AjaxRequestEvent::NAME => ['onXmlHttpRequest', self::PRIORITY],
        ];
    }

    public function onXmlHttpRequest(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->isXmlHttpRequest()) {
            $action = $request->request->get('action', null);
            $alias = str_replace('Request', '', $action);

            if (\array_key_exists($alias, $this->resources)) {
                /** @var ControllerInterface $controller */
                $controller = $this->get($alias);
                $controller->generateResponse($ajaxRequestEvent);
            } else {
                throw new \Exception(sprintf('Could not find Controller for action "%s".', $action));
            }
        }
    }
}
