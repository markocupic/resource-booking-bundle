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
 * @property bool                              $isDateInPermittedRange
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
        return $this->arrData[$strKey] ?? null;
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

        $this->arrData['timeSlotId'] = $timeSlotId;
        $this->arrData['userIsLoggedIn'] = (bool) $this->user;
        $this->arrData['resource'] = $resource->row();
        $this->arrData['startTime'] = $startTime;
        $this->arrData['endTime'] = $endTime;
        $this->arrData['itemsBooked'] = $desiredItems;
        $this->arrData['isBlocked'] = false;
        // This is the timestamp of a "beginn week weekday" by default this is a monday
        $this->arrData['bookingRepeatStopWeekTstamp'] = null === $bookingRepeatStopWeekTstamp ? $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $startTime) : $bookingRepeatStopWeekTstamp;
        $this->arrData['pid'] = $resource->id;
        $this->arrData['isDateInPermittedRange'] = $this->isDateInPermittedRange();
        $this->arrData['weekday'] = strtolower(date('l', $startTime));
        $this->arrData['startTimeString'] = UtcTimeHelper::parseStartTime($startTime);
        $this->arrData['endTimeString'] = UtcTimeHelper::parseEndTime($endTime);
        $this->arrData['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $startTime);
        $this->arrData['datimSpanString'] = \sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $startTime), $dateAdapter->parse($configAdapter->get('dateFormat'), $startTime), UtcTimeHelper::parseStartTime($startTime), UtcTimeHelper::parseEndTime($endTime));
        $this->arrData['timeSpanString'] = UtcTimeHelper::parseStartTime($startTime).' - '.UtcTimeHelper::parseEndTime($endTime);
        $this->arrData['beginnWeekTimestampSelectedWeek'] = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $startTime);
        $this->arrData['isBookable'] = $this->isBookable();
        $this->arrData['enoughItemsAvailable'] = $this->areEnoughItemsAvailable();
        $this->arrData['itemsStillAvailable'] = $this->getItemsAvailable();
        $this->arrData['isFullyBooked'] = $this->isFullyBooked();
        $this->arrData['hasBookings'] = $this->hasBookings();
        $this->arrData['bookings'] = $this->getBookings();
        $this->arrData['bookingCount'] = $this->getBookingCount();
        $this->arrData['userHasBooked'] = $this->isBookedByLoggedInUser();
        $this->arrData['userBooking'] = $this->getUserBooking();
        $this->arrData['dataBooking'] = [];
        $this->arrData['isCancelable'] = $this->isCancelable();

        return $this;
    }

    public function row(): array
    {
        return $this->arrData;
    }

    /**
     * @throws \Exception
     */
    public function isDateInPermittedRange(): bool
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

    public function areEnoughItemsAvailable(): bool
    {
        $totalBookedItems = 0;

        foreach ($this->getBookings() as $booking) {
            if ($this->isBookingForLoggedUser($booking)) {
                continue;
            }

            $totalBookedItems += (int) $booking['itemsBooked'] ?? 1;
        }

        if ($totalBookedItems + $this->arrData['itemsBooked'] > (int) $this->resource['itemsAvailable']) {
            return false;
        }

        return true;
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
                $arrBookings[] = $bookings->row();
            }
        }

        $this->arrData['bookings'] = $arrBookings;

        return $this->arrData['bookings'];
    }

    public function getItemsAvailable(): int
    {
        if (isset($this->arrData['itemsStillAvailable'])) {
            return $this->arrData['itemsStillAvailable'];
        }

        $totalBookedItems = 0;

        foreach ($this->getBookings() as $booking) {
            $totalBookedItems += (int) $booking['itemsBooked'] ?? 1;
        }

        $this->arrData['itemsStillAvailable'] = (int) $this->resource['itemsAvailable'] - $totalBookedItems;

        return $this->arrData['itemsStillAvailable'];
    }

    public function isFullyBooked(): bool
    {
        $totalBookedItems = 0;

        foreach ($this->getBookings() as $booking) {
            $totalBookedItems += (int) $booking['itemsBooked'] ?? 1;
        }

        if ($totalBookedItems >= (int) $this->resource['itemsAvailable']) {
            return true;
        }

        return false;
    }

    public function hasBookings(): bool
    {
        return !empty($this->getBookings());
    }

    public function getBookingCount(): int
    {
        if (!$this->hasBookings()) {
            return 0;
        }

        return \count($this->getBookings());
    }

    public function isBookedByLoggedInUser(): bool
    {
        foreach ($this->getBookings() as $booking) {
            if ($this->isBookingForLoggedUser($booking)) {
                return true;
            }
        }

        return false;
    }

    public function getUserBooking(): array|null
    {
        if (!$this->isBookedByLoggedInUser()) {
            return null;
        }

        foreach ($this->getBookings() as $booking) {
            if ($this->isBookingForLoggedUser($booking)) {
                return $this->framework
                    ->getAdapter(ResourceBookingModel::class)
                    ->findById($booking['id'])->row()
                ;
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function isCancelable(): bool
    {
        if ($this->isBlocked()) {
            return false;
        }

        $arrBookings = $this->getBookings();

        if (empty($arrBookings)) {
            return false;
        }

        if (!$this->isDateInPermittedRange()) {
            return false;
        }

        if (!$this->user) {
            return false;
        }

        return $this->isBookedByLoggedInUser();
    }

    public function isBlocked(): bool
    {
        return $this->arrData['isBlocked'];
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

    public function setIsBookable(bool $isBookable): self
    {
        $this->arrData['isBookable'] = $isBookable;

        return $this;
    }

    public function setIsCancelable(bool $isCancelable): self
    {
        $this->arrData['isCancelable'] = $isCancelable;

        return $this;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->arrData['isBlocked'] = $isBlocked;

        return $this;
    }

    protected function initializeUser(): void
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

    protected function isBookingForLoggedUser(array $booking): bool
    {
        return $this->user && $this->user->id === ($booking['member'] ?? -1);
    }
}
