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
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\Security\Core\Security;

/**
 * @property int                               $index
 * @property string                            $weekday
 * @property int                               $startTime
 * @property int                               $endTime
 * @property int                               $itemsBooked
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
 * @property bool                              $isBookable
 * @property bool                              $isCancelable
 * @property bool                              $hasEnoughItemsAvailable
 * @property bool                              $userHasBooked
 * @property array|null                        $bookingRelatedToLoggedInUser
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
    protected array $arrData = [];
    protected MemberModel|null $user = null;

    public function __construct(
        protected ContaoFramework $framework,
        protected Security $security,
        protected Utils $utils,
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
    public function create(int $timeSlotId, ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        if ($this->security->getUser() instanceof FrontendUser) {
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
            $this->user = $memberModelAdapter->findByPk($this->security->getUser()->id);
        }

        $this->arrData['timeSlotId'] = $timeSlotId;
        $this->arrData['userIsLoggedIn'] = (bool) $this->user;
        $this->arrData['resource'] = $resource->row();
        $this->arrData['startTime'] = $startTime;
        $this->arrData['endTime'] = $endTime;
        $this->arrData['itemsBooked'] = $desiredItems;

        $appConfig = $this->utils->getAppConfig();

        // Auto fill
        if (null === $bookingRepeatStopWeekTstamp) {
            $bookingRepeatStopWeekTstamp = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $this->arrData['startTime']);
        }

        // This is the timestamp of a "beginn week weekday" by default this is a monday
        $this->arrData['bookingRepeatStopWeekTstamp'] = $bookingRepeatStopWeekTstamp;
        $this->arrData['pid'] = $resource->id;
        $this->arrData['isDateInPermittedRange'] = $this->isDateInPermittedRange();
        $this->arrData['weekday'] = strtolower(date('l', $this->arrData['startTime']));
        $this->arrData['startTimeString'] = $dateAdapter->parse('H:i', $this->arrData['startTime']);
        $this->arrData['endTimeString'] = $dateAdapter->parse('H:i', $this->arrData['endTime']);
        $this->arrData['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $this->arrData['startTime']);
        $this->arrData['datimSpanString'] = sprintf('%s, %s: %s - %s', $dateAdapter->parse('D', $this->arrData['startTime']), $dateAdapter->parse($configAdapter->get('dateFormat'), $this->arrData['startTime']), $dateAdapter->parse('H:i', $this->arrData['startTime']), $dateAdapter->parse('H:i', $this->arrData['endTime']));
        $this->arrData['timeSpanString'] = $dateAdapter->parse('H:i', $this->arrData['startTime']).' - '.$dateAdapter->parse('H:i', $this->arrData['startTime']);
        $this->arrData['beginnWeekTimestampSelectedWeek'] = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig, $this->arrData['startTime']);
        $this->arrData['isBookable'] = $this->isBookable();
        $this->arrData['enoughItemsAvailable'] = $this->enoughItemsAvailable();
        $this->arrData['itemsStillAvailable'] = $this->getItemsAvailable();
        $this->arrData['isFullyBooked'] = $this->isFullyBooked();
        $this->arrData['hasBookings'] = $this->hasBookings();
        $this->arrData['bookings'] = $this->getBookings();
        $this->arrData['bookingCount'] = $this->getBookingCount();
        $this->arrData['userHasBooked'] = $this->isBookedByUser();
        $this->arrData['bookingRelatedToLoggedInUser'] = $this->getBookingRelatedToLoggedInUser();
        $this->arrData['dataBooking'] = [];
        $this->arrData['isCancelable'] = $this->isCancelable();

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function isDateInPermittedRange(): bool
    {
        if (isset($this->arrData['isDateInPermittedRange'])) {
            return $this->arrData['isDateInPermittedRange'];
        }

        if ($this->arrData['endTime'] < time()) {
            return false;
        }

        if ($this->arrData['startTime'] > strtotime('+1 week', $this->arrData['bookingRepeatStopWeekTstamp'])) {
            return false;
        }

        if ($this->utils->getModuleModel()->resourceBooking_addDateStop) {
            if ($this->arrData['endTime'] > $this->utils->getModuleModel()->resourceBooking_dateStop + 24 * 3600) {
                return false;
            }
        }

        return true;
    }

    public function enoughItemsAvailable(): bool
    {
        if (isset($this->arrData['enoughItemsAvailable'])) {
            return $this->arrData['enoughItemsAvailable'];
        }

        $itemsBooked = 0;

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            if ($this->user && (int) $this->user->id === (int) $booking['member'] ?? -1) {
                $iterator->next();

                continue;
            }

            $itemsBooked += (int) $booking['itemsBooked'] ?? 1;

            $iterator->next();
        }

        if ($itemsBooked + $this->arrData['itemsBooked'] > (int) $this->resource['itemsAvailable']) {
            return false;
        }

        return true;
    }

    public function setBookings(array $data): void
    {
        $this->arrData['bookings'] = $data;
    }

    public function getBookings(): array
    {
        if (isset($this->arrData['bookings'])) {
            return $this->arrData['bookings'];
        }

        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $bookings = $resourceBookingModelAdapter
            ->findByResourceStartTimeAndEndTime(
                $resourceBookingResourceModelAdapter->findByPk($this->arrData['resource']['id']),
                (int) $this->arrData['startTime'],
                (int) $this->arrData['endTime']
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

        $itemsBooked = 0;

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            $itemsBooked += (int) $booking['itemsBooked'] ?? 1;

            $iterator->next();
        }

        return (int) $this->resource['itemsAvailable'] - $itemsBooked;
    }

    public function isFullyBooked(): bool
    {
        if (isset($this->arrData['isFullyBooked'])) {
            return $this->arrData['isFullyBooked'];
        }

        $itemsBooked = 0;

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            $itemsBooked += (int) $booking['itemsBooked'] ?? 1;

            $iterator->next();
        }

        if ($itemsBooked >= (int) $this->resource['itemsAvailable']) {
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
        if (isset($this->arrData['bookingCount'])) {
            return $this->arrData['bookingCount'];
        }

        if (!$this->hasBookings()) {
            return 0;
        }

        return \count($this->getBookings());
    }

    public function isBookedByUser(): bool
    {
        if (isset($this->arrData['userHasBooked'])) {
            return $this->arrData['userHasBooked'];
        }

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            if ($this->user && (int) $this->user->id === (int) $booking['member'] ?? -1) {
                return true;
            }

            $iterator->next();
        }

        return false;
    }

    public function getBookingRelatedToLoggedInUser(): array|null
    {
        if (isset($this->arrData['bookingRelatedToLoggedInUser'])) {
            return $this->arrData['bookingRelatedToLoggedInUser'];
        }

        if (!$this->isBookedByUser()) {
            return null;
        }

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            if ($this->user && (int) $this->user->id === (int) $booking['member'] ?? -1) {
                $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

                return $resourceBookingModelAdapter->findByPk($booking['id'])->row();
            }

            $iterator->next();
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function isCancelable(): bool
    {
        if (isset($this->arrData['isCancelable']) && \is_bool($this->arrData['isCancelable'])) {
            return $this->arrData['isCancelable'];
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

        $iterator = (new \ArrayObject($this->getBookings()))->getIterator();

        while ($iterator->valid()) {
            $booking = $iterator->current();

            if ($this->user && (int) $this->user->id === (int) $booking['member'] ?? -1) {
                return true;
            }

            $iterator->next();
        }

        return false;
    }

    public function setBookingData(array $arrData): self
    {
        $this->arrData['dataBooking'] = $arrData;

        return $this;
    }

    public function row(): array
    {
        return $this->arrData;
    }
}
