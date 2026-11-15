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

namespace Markocupic\ResourceBookingBundle\AppState;

use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Symfony\Component\HttpFoundation\Request;

/**
 * State of the week calendar for one Ajax request: module, selected resource
 * type and resource, displayed week and the permitted weeks.
 *
 * Nothing is stored on the server: the front end sends the selection with every
 * request (resType, res, date), AppStateFactory validates it and creates this
 * object. It is attached to the request (REQUEST_ATTRIBUTE) and therefore only
 * lives as long as the request.
 */
final class AppState
{
    public const REQUEST_ATTRIBUTE = '_rbb_app_state';

    /**
     * @param array{beginWeek: string, autoSelectFirstResource: bool, permittedFormFields: list<string>} $appConfig
     */
    public function __construct(
        public readonly string $moduleKey,
        public readonly ModuleModel $module,
        public readonly PageModel $page,
        public readonly ResourceBookingResourceTypeModel|null $resourceType,
        public readonly ResourceBookingResourceModel|null $resource,
        public readonly array $appConfig,
        public readonly int $activeWeekTstamp,
        public readonly int $firstPermittedWeekTstamp,
        public readonly int $lastPermittedWeekTstamp,
        public readonly string $language,
    ) {
    }

    /**
     * Returns the state attached to the request by the week calendar module.
     *
     * @throws \LogicException if the request has no state (not an Ajax request of the week calendar)
     */
    public static function fromRequest(Request $request): self
    {
        $state = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if (!$state instanceof self) {
            throw new \LogicException('The request has no state of the resource booking week calendar.');
        }

        return $state;
    }

    public function attachTo(Request $request): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $this);
    }

    public function getModuleId(): int
    {
        return (int) $this->module->id;
    }

    public function getPageId(): int
    {
        return (int) $this->page->id;
    }

    public function getResourceTypeId(): int
    {
        return null === $this->resourceType ? 0 : (int) $this->resourceType->id;
    }

    public function getResourceId(): int
    {
        return null === $this->resource ? 0 : (int) $this->resource->id;
    }

    public function getBeginWeek(): string
    {
        return $this->appConfig['beginWeek'];
    }

    /**
     * Form fields of the booking form that may be submitted (app configuration).
     *
     * @return list<string>
     */
    public function getPermittedFormFields(): array
    {
        return $this->appConfig['permittedFormFields'];
    }
}
