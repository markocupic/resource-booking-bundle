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
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
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
 * @property int|null                          $beginnWeekTimestampSelectedWeek
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
    public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, int|null $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $appConfig = $this->utils->getAppConfig();

        $this->initializeUser();

        $startTimeString = UtcTimeHelper::parseStartTime($startTime);
        $endTimeString = UtcTimeHelper::parseEndTime($endTime);
        $dateString = $dateAdapter->parse($configAdapter->get('dateFormat'), $startTime);
        $firstDayOfWeek = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $startTime);

        $this->arrData = [
            'timeSlotId' => $timeSlotId,
            'userIsLoggedIn' => (bool) $this->user,
            'resource' => $resource->row(),
            'pid' => $resource->id,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'itemsBooked' => $desiredItems,
            // Timestamp of the "begin week weekday" (Monday by default)
            'bookingRepeatStopWeekTstamp' => $bookingRepeatStopWeekTstamp ?? $firstDayOfWeek,
            'weekday' => strtolower(date('l', $startTime)),
            'startTimeString' => $startTimeString,
            'endTimeString' => $endTimeString,
            'date' => $dateString,
            'datimSpanString' => \sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $startTime), $dateString, $startTimeString, $endTimeString),
            'timeSpanString' => $startTimeString.' - '.$endTimeString,
            'beginnWeekTimestampSelectedWeek' => $firstDayOfWeek,
            'dataBooking' => [],
        ];

        // Order matters below — each call depends on the previous assignments
        $this->arrData['isWithinAllowedDateRange'] = $this->isWithinAllowedDateRange();
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

        if ($this->utils->getModuleModel()->resourceBooking_addDateStop) {
            if ($this->arrData['endTime'] > $this->utils->getModuleModel()->resourceBooking_dateStop + self::SECONDS_IN_DAY) {
                return false;
            }
        }

        return true;
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
