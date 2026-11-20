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
use Markocupic\ResourceBookingBundle\Booking\BookingRepository;
use Markocupic\ResourceBookingBundle\Booking\CancellationRules;
use Markocupic\ResourceBookingBundle\DependencyInjection\Attribute\AsAjaxController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxStatus;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Opens the cancel window for a booking of the logged-in member:
 * POST /_rbb/api/actions/cancelBookingFormRequest with the field bookingId. The window itself
 * is rendered by the RenderBookingWindowListener. Nothing is changed (see CancelController).
 */
#[AsAjaxController(self::ACTION)]
final class CancelBookingFormController extends AbstractController implements ControllerInterface
{
    public const ACTION = 'cancelBookingFormRequest';

    public function __construct(
        ContaoFramework $framework,
        LoggedInFrontendUser $user,
        private readonly TranslatorInterface $translator,
        private readonly BookingRepository $bookingRepository,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
        parent::__construct($framework, $user);
    }

    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $this->framework->getAdapter(System::class)->loadLanguageFile('default', $this->translator->getLocale());

        $user = $this->user->getLoggedInUser();
        $bookingId = $request->request->all()['bookingId'] ?? null;
        $bookingId = \is_scalar($bookingId) && preg_match('/^\d+$/', (string) $bookingId) ? (int) $bookingId : 0;
        $booking = null === $user || $bookingId <= 0 ? null : $this->bookingRepository->findById($bookingId);

        if (null === $user || null === $booking || !CancellationRules::isOwnedBy($booking->row(), (int) $user->id)) {
            $ajaxResponse->setStatus(AjaxStatus::Error);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.notAuthorized', [], 'contao_default'));

            return $ajaxResponse;
        }

        if (!CancellationRules::isCancelableByTime($booking->row(), $this->clock->now()->getTimestamp())) {
            $ajaxResponse->setStatus(AjaxStatus::Error);
            $ajaxResponse->setErrorMessage($this->translator->trans('RBB.ERR.cancelingPastBookingNotPossible', [], 'contao_default'));

            return $ajaxResponse;
        }

        $ajaxResponse->setStatus(AjaxStatus::Success);
        $ajaxResponse->setData('booking', ['id' => (int) $booking->id, 'bookingUuid' => (string) $booking->bookingUuid]);

        return $ajaxResponse;
    }
}
