<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\Traits\RefreshDataTrait;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RefreshDataController extends AbstractController implements ControllerInterface
{
    use RefreshDataTrait;

    private SlotFactory $slotFactory;
    private TranslatorInterface $translator;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->getRefreshedData());
    }
}
