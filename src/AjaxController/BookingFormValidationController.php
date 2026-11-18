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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\ResourceBookingBundle\AjaxController\Traits\BookingTrait;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use Markocupic\ResourceBookingBundle\WeekCalendar\SlotPresenter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class BookingFormValidationController extends AbstractController implements ControllerInterface
{
    use BookingTrait;

    public const ACTION = 'bookingFormValidationRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        Utils $utils,
        private readonly TranslatorInterface $translator,
        private readonly SlotFactory $slotFactory,
        private readonly WeekBoundaries $weekBoundaries,
        private readonly SlotPresenter $slotPresenter,
    ) {
        parent::__construct($framework, $user, $utils);
    }

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework
            ->getAdapter(System::class)
            ->loadLanguageFile('default', $this->translator->getLocale())
        ;

        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setData('noDatesSelected', false);
        $ajaxResponse->setData('resourceIsAlreadyFullyBooked', false);
        $ajaxResponse->setData('bookingValidationProcessSucceeded', true);
        $ajaxResponse->setData('noBookingRepeatStopWeekTstampSelected', false);

        try {
            $appState = AppState::fromRequest($request);
            $this->getLoggedInUserOrThrow();
            $resource = $this->getRequestedResource($request, $appState);
            $slots = $this->getSlotCollectionFromRequest($request, $resource, $appState, self::getBookingRepeatStopWeekTstamp($request), Uuid::uuid4()->toString());
        } catch (InvalidBookingRequestException $e) {
            return $this->setFailedResponse($ajaxResponse, $this->translator->trans($e->getTranslationKey(), [$e->getField()], 'contao_default'));
        } catch (\Exception) {
            return $this->setFailedResponse($ajaxResponse, $this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default'));
        }

        if (null === ($errorKey = $this->getBookingErrorKey($slots))) {
            $ajaxResponse->setConfirmationMessage($this->translator->trans('RBB.MSG.resourceAvailable', [], 'contao_default'));
        } else {
            $ajaxResponse->setData('bookingValidationProcessSucceeded', false);
            $ajaxResponse->setErrorMessage($this->translator->trans($errorKey, [], 'contao_default'));
        }

        // The booking preview contains no bookings of other members (SlotPresenter)
        $ajaxResponse->setData('slotSelection', array_map(
            $this->slotPresenter->present(...),
            $slots->all(),
        ));

        if ($ajaxResponse->hasErrorMessage()) {
            $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        }

        return $ajaxResponse;
    }

    private function setFailedResponse(AjaxResponse $ajaxResponse, string $errorMessage): AjaxResponse
    {
        $ajaxResponse->setStatus(AjaxResponse::STATUS_ERROR);
        $ajaxResponse->setData('bookingValidationProcessSucceeded', false);
        $ajaxResponse->setData('slotSelection', []);
        $ajaxResponse->setErrorMessage($errorMessage);

        return $ajaxResponse;
    }
}
