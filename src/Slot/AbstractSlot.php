<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Slot;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\Security\Core\Security;

/**
 * Class AbstractSlot.
 *
 * @property int                       $index
 * @property string                    $weekday
 * @property int                       $startTimestamp
 * @property int                       $endTimestamp
 * @property int|null                  $bookingRepeatStopWeekTstamp
 * @property int|null                  $mondayTimestampSelectedWeek
 * @property string                    $startTimeString
 * @property string                    $endTimeString
 * @property MemberModel|null          $user
 * @property string                    $timeSpanString
 * @property bool                      $hasBookings
 * @property bool                      $isBookable
 * @property bool                      $isCancelable
 * @property bool                      $userHasBooked
 * @property ResourceBookingModel|null $bookingRelatedToLoggedInUser
 * @property int                       $timeSlotId
 * @property int                       $resourceId
 * @property bool                      $isFullyBooked
 * @property bool                      $isValidDate
 * @property string                    $cssClass
 * @property Collection|null           $bookings
 * @property int                       $bookingCount
 *
 * properties from booking main
 * @property string $bookingCheckboxValue
 * @property string $bookingCheckboxId
 */
abstract class AbstractSlot implements SlotInterface
{
    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Security
     */
    protected $security;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * @var array
     */
    protected $arrData = [];

    public function __construct(ContaoFramework $framework, Security $security, Utils $utils)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->utils = $utils;
    }

    /**
     * @param $strKey
     * @param mixed $value
     */
    public function __set(string $strKey, $value): void
    {
        $this->arrData[$strKey] = $value;
    }

    /**
     * @param $strKey
     *
     * @return mixed|null
     */
    public function __get(string $strKey)
    {
        return $this->arrData[$strKey] ?? null;
    }

    public function create(ResourceBookingResourceModel $resource, int $startTimestamp, int $endTimestamp, int $desiredItems = 1, int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $this->arrData['resource'] = $resource;
        $this->arrData['startTimestamp'] = $startTimestamp;
        $this->arrData['endTimestamp'] = $endTimestamp;
        $this->arrData['desiredItems'] = $desiredItems;

        if (null === $bookingRepeatStopWeekTstamp) {
            $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
            $bookingRepeatStopWeekTstamp = $dateHelperAdapter->getMondayOfCurrentWeek($this->arrData['startTimestamp']);
        }

        // This is the timestamp of a monday,
        // All bookings in the current week are allowed
        $this->arrData['bookingRepeatStopWeekTstamp'] = $bookingRepeatStopWeekTstamp;

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var MemberModel user */
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
            $this->user = $memberModelAdapter->findByPk($this->security->getUser()->id);
        }

        $this->setBookings();

        return $this;
    }

    public function hasBookings(): bool
    {
        return null !== $this->arrData['bookings'];
    }

    public function getBookings(): ?Collection
    {
        if (null === $this->arrData['bookings']) {
            $this->setBookings();
        }

        if (null !== $this->arrData['bookings']) {
            $this->arrData['bookings']->reset();
        }

        return $this->arrData['bookings'];
    }

    public function getBookingCount(): int
    {
        if (!$this->hasBookings()) {
            return 0;
        }

        return $this->getBookings()->count();
    }

    public function getEndTime(): int
    {
        return $this->arrData['endTimestamp'];
    }

    public function getStartTime(): int
    {
        return $this->arrData['startTimestamp'];
    }

    public function getDesiredItems(): int
    {
        return $this->arrData['desiredItems'];
    }

    public function getResource(): ?ResourceBookingResourceModel
    {
        return $this->arrData['resource'];
    }

    public function getRepetitionStop(): int
    {
        return $this->repetitionStop;
    }

    public function isBookedByUser(): bool
    {
        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    return true;
                }
            }
        }

        return false;
    }

    public function enoughItemsAvailable(): bool
    {
        $count = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    continue;
                }
                $count += (int) $objBookings->itemsBooked;
            }
        }

        if ($count + $this->arrData['desiredItems'] > (int) $this->getResource()->itemsAvailable) {
            return false;
        }

        return true;
    }

    public function isFullyBooked(): bool
    {
        $count = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                $count += (int) $objBookings->itemsBooked;
            }
        }

        if ($count >= (int) $this->getResource()->itemsAvailable) {
            return true;
        }

        return false;
    }

    public function getBookingRelatedToLoggedInUser(): ?ResourceBookingModel
    {
        if (!$this->isBookedByUser()) {
            return null;
        }

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    return $objBookings->current();
                }
            }
        }

        return null;
    }

    public function hasValidDate(): bool
    {
        if ($this->arrData['endTimestamp'] < time()) {
            return false;
        }

        if ($this->arrData['startTimestamp'] > strtotime('+1 week', $this->arrData['bookingRepeatStopWeekTstamp'])) {
            return false;
        }

        if ($this->utils->getModuleModel()->resourceBooking_addDateStop) {
            if ($this->arrData['endTimestamp'] > $this->utils->getModuleModel()->resourceBooking_dateStop + 24 * 3600) {
                return false;
            }
        }

        return true;
    }

    public function row()
    {
        $arrReturn = [];

        foreach ($this->arrData as $k => $v) {
            if ($v instanceof Collection) {
                $v = $v->fetchAll();
            }
            $arrReturn[$k] = $v;
        }

        return $arrReturn;
    }

    protected function setBookings(): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);
        $this->arrData['bookings'] = $resourceBookingModelAdapter
            ->findByResourceStarttimeAndEndtime($this->arrData['resource'], $this->arrData['startTimestamp'], $this->arrData['endTimestamp'])
        ;
    }
}
