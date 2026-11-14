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

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Validator\BookingRequestValidator;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\ResourceBookingRestriction;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @property int                               $index
 * @property string                            $weekday
 * @property int                               $startTime
 * @property int                               $endTime
 * @property int                               $totalBookedItems
 * @property string                            $date
 * @property int|null                          $bookingRepeatStopWeekTstamp
 * @property int|null                          $beginWeekTimestampSelectedWeek
 * @property int|null                          $lastPermittedWeekTstamp
 * @property string                            $startTimeString
 * @property string                            $endTimeString
 * @property MemberModel|null                  $user
 * @property bool                              $userIsLoggedIn
 * @property string                            $timeSpanString
 * @property string                            $datimSpanString
 * @property bool                              $hasBookings
 * @property bool                              $isBlocked
 * @property bool                              $isBookable
 * @property bool                              $isCancelable
 * @property bool                              $hasEnoughItemsAvailable
 * @property bool                              $userHasBooked
 * @property array|null                        $userBooking
 * @property int                               $timeSlotId
 * @property ResourceBookingResourceModel|null $resource
 * @property int                               $pid
 * @property bool                              $isFullyBooked
 * @property bool                              $isWithinAllowedDateRange
 * @property bool                              $isTimeSlotAvailable
 * @property string|null                       $timeSlotWeekdays
 * @property array|null                        $blockedPeriod
 * @property bool                              $isInBlockedPeriod
 * @property bool                              $isValidTimeSlot
 * @property bool                              $hasValidItemCount
 * @property string                            $blockedPeriodDescription
 * @property string                            $cssClass
 * @property Collection|null                   $bookings
 * @property int                               $bookingCount
 * @property string                            $bookingUuid
 * @property array                             $dataBooking
 * @property int                               $itemsAvailable
 *
 * properties from booking main
 * @property string $bookingCheckboxValue
 * @property string $bookingCheckboxId
 */
abstract class AbstractSlot implements SlotInterface
{
    protected const SECONDS_IN_DAY = 86400;

    protected array $arrData = [];

    protected MemberModel|null $user = null;

    public function __construct(
        protected readonly ContaoFramework $framework,
        protected readonly TokenStorageInterface $tokenStorage,
        protected readonly Utils $utils,
    ) {
    }

    /**
     * @return mixed|null
     */
    public function __get(string $strKey)
    {
        return match ($strKey) {
            'isBlocked' => !empty($this->getBlockedBookings()),
            default => $this->arrData[$strKey] ?? null,
        };
    }

    /**
     * @throws \Exception
     */
    public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $requestedItems = 1, int|null $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        $this->initializeUser();

        $startTimeString = UtcTimeHelper::parseStartTime($startTime);
        $endTimeString = UtcTimeHelper::parseEndTime($endTime);
        $dateString = $dateAdapter->parse($configAdapter->get('dateFormat'), $startTime);
        $timeSlot = $this->framework->getAdapter(ResourceBookingTimeSlotModel::class)->findById($timeSlotId);
        $timeSlotType = null === $timeSlot ? null : $this->framework->getAdapter(ResourceBookingTimeSlotTypeModel::class)->findById((int) $timeSlot->pid);

        // The first day of the week is set in the timetable of the resource.
        $resourceTimeSlotType = null !== $timeSlotType && (int) $timeSlotType->id === (int) $resource->timeSlotType
            ? $timeSlotType
            : $this->framework->getAdapter(ResourceBookingTimeSlotTypeModel::class)->findById((int) $resource->timeSlotType);
        $appConfig = ['beginWeek' => ResourceBookingRestriction::getBeginWeek($resourceTimeSlotType)];
        $firstDayOfWeek = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $startTime);
        $blockedPeriod = $this->findBlockedPeriod($resource->row(), $startTime, $endTime);

        $this->arrData = [
            'timeSlotId' => $timeSlotId,
            // Weekdays on which the time slot is available (tl_resource_booking_time_slot.weekdays)
            'timeSlotWeekdays' => $timeSlot?->weekdays,
            // The time slot exists, is published, belongs to the (published) timetable of the resource
            // and the start and end time match the time slot (protection against manipulated requests)
            'isValidTimeSlot' => ResourceBookingRestriction::isValidTimeSlot($timeSlot?->row(), $timeSlotType?->row(), $resource->row(), $startTime, $endTime),
            'hasValidItemCount' => BookingRequestValidator::isValidItemCount($requestedItems, (int) $resource->itemsAvailable),
            // Blocked period covering this slot, e.g. holidays (tl_resource_booking_blocked_period).
            // The title is an internal name and is not sent to the front end, only the description is displayed.
            'blockedPeriod' => null === $blockedPeriod ? null : ['id' => (int) $blockedPeriod['id']],
            'isInBlockedPeriod' => null !== $blockedPeriod,
            'blockedPeriodDescription' => null === $blockedPeriod ? '' : trim(StringUtil::decodeEntities((string) ($blockedPeriod['description'] ?? ''))),
            'userIsLoggedIn' => (bool) $this->user,
            'resource' => $resource->row(),
            'pid' => $resource->id,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'itemsBooked' => $requestedItems,
            // Timestamp of the "begin week weekday" (Monday by default)
            'bookingRepeatStopWeekTstamp' => $bookingRepeatStopWeekTstamp ?? $firstDayOfWeek,
            'weekday' => strtolower(date('l', $startTime)),
            'startTimeString' => $startTimeString,
            'endTimeString' => $endTimeString,
            'date' => $dateString,
            'datimSpanString' => \sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $startTime), $dateString, $startTimeString, $endTimeString),
            'timeSpanString' => $startTimeString.' - '.$endTimeString,
            'beginWeekTimestampSelectedWeek' => $firstDayOfWeek,
            // Timestamp of the first day of the last week the resource can be booked (tl_resource_booking_resource.weeksAhead)
            'lastPermittedWeekTstamp' => $dateHelperAdapter->addWeeksToTime(ResourceBookingRestriction::getWeeksAhead($resource), $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig)),
            'dataBooking' => [],
        ];

        // Order matters below — each call depends on the previous assignments
        $this->arrData['isWithinAllowedDateRange'] = $this->isWithinAllowedDateRange();
        $this->arrData['isTimeSlotAvailable'] = $this->isTimeSlotAvailable();
        $this->arrData['bookings'] = $this->getBookings();
        $this->arrData['isBlocked'] = $this->isBlocked;
        $this->arrData['isBookable'] = $this->isBookable();
        $this->arrData['enoughItemsAvailable'] = $this->canFulfillRequestedItems();
        $this->arrData['itemsStillAvailable'] = $this->getRemainingItems();
        $this->arrData['isFullyBooked'] = $this->isFullyBooked();
        $this->arrData['hasBookings'] = $this->hasAnyBookings();
        $this->arrData['bookingCount'] = $this->countBookings();
        $this->arrData['userHasBooked'] = $this->userHasBooking();
        $this->arrData['userBooking'] = $this->getUserBooking();
        $this->arrData['isCancelable'] = $this->isCancelable();

        return $this;
    }

    public function setRow(array $data): void
    {
        $this->arrData = $data;
    }

    public function row(): array
    {
        return $this->arrData;
    }

    /**
     * @throws \Exception
     */
    public function isWithinAllowedDateRange(): bool
    {
        if ($this->arrData['endTime'] < time()) {
            return false;
        }

        if ($this->arrData['startTime'] > strtotime('+1 week', $this->arrData['bookingRepeatStopWeekTstamp'])) {
            return false;
        }

        if ($this->isOutsideBookingPeriod()) {
            return false;
        }

        return true;
    }

    /**
     * Returns true if the slot lies after the booking period of the resource,
     * i.e. after the last week that can be booked (tl_resource_booking_resource.weeksAhead)
     * or after the "bookable until" date (tl_resource_booking_resource.bookableUntil).
     */
    public function isOutsideBookingPeriod(): bool
    {
        // The resource can only be booked a certain number of weeks ahead.
        if (isset($this->arrData['lastPermittedWeekTstamp'], $this->arrData['beginWeekTimestampSelectedWeek']) && $this->arrData['beginWeekTimestampSelectedWeek'] > $this->arrData['lastPermittedWeekTstamp']) {
            return true;
        }

        // The resource can only be booked up to and including the "bookable until" date.
        $bookableUntil = ResourceBookingRestriction::getBookableUntil($this->arrData['resource']);

        return null !== $bookableUntil && $this->arrData['endTime'] > $bookableUntil + self::SECONDS_IN_DAY;
    }

    /**
     * Returns false if the time slot is not available on the weekday of this slot
     * (tl_resource_booking_time_slot.weekdays).
     */
    public function isTimeSlotAvailable(): bool
    {
        return ResourceBookingRestriction::isTimeSlotAvailableOnWeekday(['weekdays' => $this->arrData['timeSlotWeekdays'] ?? null], $this->arrData['weekday']);
    }

    /**
     * Returns true if the slot belongs to a published time slot of the published timetable
     * of the resource and its start and end time match the time slot.
     */
    public function isValidTimeSlot(): bool
    {
        return true === ($this->arrData['isValidTimeSlot'] ?? false);
    }

    /**
     * Returns true if the number of requested items is between 1 and the number
     * of available items of the resource.
     */
    public function hasValidItemCount(): bool
    {
        return BookingRequestValidator::isValidItemCount((int) ($this->arrData['itemsBooked'] ?? 0), (int) ($this->arrData['resource']['itemsAvailable'] ?? 0));
    }

    /**
     * Returns true if a blocked period (e.g. holidays) covers this slot.
     */
    public function isInBlockedPeriod(): bool
    {
        return !empty($this->arrData['blockedPeriod']);
    }

    public function canFulfillRequestedItems(): bool
    {
        $booked = $this->countBookedItems(excludeOwn: true);

        return $booked + $this->arrData['itemsBooked'] <= (int) $this->resource['itemsAvailable'];
    }

    public function getBookings(): array
    {
        if (isset($this->arrData['bookings'])) {
            return $this->arrData['bookings'];
        }

        $bookings = $this->framework
            ->getAdapter(ResourceBookingModel::class)
            ->findByResourceStartTimeAndEndTime(
                $this->framework
                    ->getAdapter(ResourceBookingResourceModel::class)
                    ->findById($this->arrData['resource']['id']),
                (int) $this->arrData['startTime'],
                (int) $this->arrData['endTime'],
            )
        ;

        $arrBookings = [];

        if (null !== $bookings) {
            while ($bookings->next()) {
                $arrBookings[] = $bookings->current()->row();
            }
        }

        $this->arrData['bookings'] = $arrBookings;

        return $this->arrData['bookings'];
    }

    public function isFullyBooked(): bool
    {
        return $this->countBookedItems() >= (int) $this->resource['itemsAvailable'];
    }

    /**
     * @throws \Exception
     */
    public function isCancelable(): bool
    {
        if (!$this->user) {
            return false;
        }

        if (empty($this->getBookings())) {
            return false;
        }

        if (!$this->isWithinAllowedDateRange()) {
            return false;
        }

        return $this->userHasBooking();
    }

    public function isBlocked(): bool
    {
        return !empty($this->getBlockedBookings());
    }

    public function setBookings(array $data): void
    {
        $this->arrData['bookings'] = $data;
    }

    public function setBookingData(array $arrData): self
    {
        $this->arrData['dataBooking'] = $arrData;

        return $this;
    }

    abstract public function isBookable(): bool;

    protected function isOwnedByLoggedUser(array $booking): bool
    {
        return $this->user && (int) $this->user->id === (int) ($booking['member'] ?? -1);
    }

    private function countBookedItems(bool $excludeOwn = false): int
    {
        $total = 0;

        foreach ($this->getBookings() as $booking) {
            if ($excludeOwn && $this->isOwnedByLoggedUser($booking)) {
                continue;
            }
            $total += (int) ($booking['itemsBooked'] ?? 1);
        }

        return $total;
    }

    private function getBlockedBookings(): array
    {
        return array_values(array_filter($this->getBookings(), static fn (array $b) => (bool) ($b['isBlocked'] ?? false)));
    }

    private function getRemainingItems(): int
    {
        return (int) $this->resource['itemsAvailable'] - $this->countBookedItems();
    }

    private function hasAnyBookings(): bool
    {
        return !empty($this->getBookings());
    }

    private function countBookings(): int
    {
        return \count($this->getBookings());
    }

    private function userHasBooking(): bool
    {
        foreach ($this->getBookings() as $booking) {
            if ($this->isOwnedByLoggedUser($booking)) {
                return true;
            }
        }

        return false;
    }

    private function getUserBooking(): array|null
    {
        foreach ($this->getBookings() as $booking) {
            if ($this->isOwnedByLoggedUser($booking)) {
                return $this->framework
                    ->getAdapter(ResourceBookingModel::class)
                    ->findById($booking['id'])?->row()
                ;
            }
        }

        return null;
    }

    /**
     * Returns the first published blocked period that covers the slot, or null.
     *
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>|null
     */
    private function findBlockedPeriod(array $resource, int $startTime, int $endTime): array|null
    {
        $periods = $this->framework
            ->getAdapter(ResourceBookingBlockedPeriodModel::class)
            ->findPublishedRows()
        ;

        foreach ($periods as $period) {
            if (ResourceBookingRestriction::isInBlockedPeriod($period, $resource, $startTime, $endTime)) {
                return $period;
            }
        }

        return null;
    }

    private function initializeUser(): void
    {
        if (null === ($token = $this->tokenStorage->getToken())) {
            return;
        }

        $user = $token->getUser();

        if ($user instanceof FrontendUser) {
            $this->user = $this->framework
                ->getAdapter(MemberModel::class)
                ->findById($user->id)
            ;
        }
    }
}
