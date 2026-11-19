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
use Markocupic\ResourceBookingBundle\Api\Input\BookingInput;
use Markocupic\ResourceBookingBundle\Api\Output\BookingPreview;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\Booking\BookingRequestHandler;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Validator\InvalidBookingRequestException;
use Markocupic\ResourceBookingBundle\WeekCalendar\SlotPresenter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsAjaxController(self::ACTION)]
final class BookingFormValidationController extends AbstractController implements ControllerInterface
{
    public const ACTION = 'bookingFormValidationRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        private readonly TranslatorInterface $translator,
        private readonly BookingRequestHandler $bookingRequestHandler,
        private readonly SlotPresenter $slotPresenter,
    ) {
        parent::__construct($framework, $user);
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

        try {
            $appState = AppState::fromRequest($request);
            $this->getLoggedInUserOrThrow();
            $input = BookingInput::fromRequest($request, $appState->getPermittedFormFields());
            $resource = $this->bookingRequestHandler->getResource($input, $appState);
            $slots = $this->bookingRequestHandler->createSlots($input, $resource, $appState, Uuid::uuid4()->toString());
        } catch (InvalidBookingRequestException $e) {
            return $this->setFailedResponse($ajaxResponse, $this->translator->trans($e->getTranslationKey(), [$e->getField()], 'contao_default'));
        } catch (\Exception) {
            return $this->setFailedResponse($ajaxResponse, $this->translator->trans('RBB.ERR.somethingWentWrong', [], 'contao_default'));
        }

        $errorKey = $this->bookingRequestHandler->getErrorKey($slots);

        if (null === $errorKey) {
            $ajaxResponse->setStatus(AjaxStatus::Success);
            $ajaxResponse->setConfirmationMessage($this->translator->trans('RBB.MSG.resourceAvailable', [], 'contao_default'));
        } else {
            $ajaxResponse->setStatus(AjaxStatus::Error);
            $ajaxResponse->setErrorMessage($this->translator->trans($errorKey, [], 'contao_default'));
        }

        // The booking preview contains no bookings of other members (SlotPresenter)
        $ajaxResponse->setResult(new BookingPreview(
            null === $errorKey,
            array_map($this->slotPresenter->present(...), $slots->all()),
        ));

        return $ajaxResponse;
    }

    private function setFailedResponse(AjaxResponse $ajaxResponse, string $errorMessage): AjaxResponse
    {
        $ajaxResponse->setStatus(AjaxStatus::Error);
        $ajaxResponse->setErrorMessage($errorMessage);
        $ajaxResponse->setResult(new BookingPreview(false));

        return $ajaxResponse;
    }
}
