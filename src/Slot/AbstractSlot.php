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

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Model;
use Contao\Model\Collection;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\Security\Core\Security;

/**
 * Class AbstractSlot.
 *
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
 * @property ResourceBookingModel|null         $bookingRelatedToLoggedInUser
 * @property int                               $timeSlotId
 * @property ResourceBookingResourceModel|null $resource
 * @property int                               $pid
 * @property bool                              $isFullyBooked
 * @property bool                              $isDateInPermittedRange
 * @property string                            $cssClass
 * @property Collection|null                   $bookings
 * @property int                               $bookingCount
 * @property string                            $bookingUuid
 * @property array                             $newBooking
 * @property int                               $itemsAvailable
 *
 * properties from booking main
 * @property string $bookingCheckboxValue
 * @property string $bookingCheckboxId
 */
abstract class AbstractSlot implements SlotInterface
{
    protected ContaoFramework $framework;

    protected Security $security;

    protected Utils $utils;

    protected array $arrData = [];

    protected ?MemberModel $user = null;

    /**
     * @required
     */
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

    public function create(ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $itemsBooked = 1, int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var MemberModel user */
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
            $this->user = $memberModelAdapter->findByPk($this->security->getUser()->id);
        }

        $this->arrData['userIsLoggedIn'] = $this->user ? true : false;
        $this->arrData['resource'] = $resource;
        $this->arrData['startTime'] = $startTime;
        $this->arrData['endTime'] = $endTime;
        $this->arrData['itemsBooked'] = $itemsBooked;

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
        $this->arrData['newBooking'] = [];
        $this->arrData['isCancelable'] = $this->isCancelable();

        return $this;
    }

    public function hasBookings(): bool
    {
        return null !== $this->getBookings();
    }

    public function getBookings(): ?Collection
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        return $resourceBookingModelAdapter
            ->findByResourceStarttimeAndEndtime(
                $this->arrData['resource'],
                $this->arrData['startTime'],
                $this->arrData['endTime']
            )
        ;
    }

    public function getBookingCount(): int
    {
        if (!$this->hasBookings()) {
            return 0;
        }

        return $this->getBookings()->count();
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

        if ($count + $this->arrData['itemsBooked'] > (int) $this->resource->itemsAvailable) {
            return false;
        }

        return true;
    }

    public function getItemsAvailable(): int
    {
        $count = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                $count += (int) $objBookings->itemsBooked;
            }
        }

        return (int) $this->resource->itemsAvailable - $count;
    }

    public function isFullyBooked(): bool
    {
        $count = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                $count += (int) $objBookings->itemsBooked;
            }
        }

        if ($count >= (int) $this->resource->itemsAvailable) {
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

    public function isDateInPermittedRange(): bool
    {
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

    public function row()
    {
        $arrReturn = [];

        foreach ($this->arrData as $k => $v) {
            if ($v instanceof Model) {
                $v = $v->row();
            } elseif ($v instanceof Collection) {
                $v = $v->fetchAll();
            }
            $arrReturn[$k] = $v;
        }

        return $arrReturn;
    }

    public function isCancelable(): bool
    {
        $bookings = $this->getBookings();

        if (null === $bookings) {
            return false;
        }

        if (!$this->isDateInPermittedRange()) {
            return false;
        }

        if (!$this->user) {
            return false;
        }

        while ($bookings->next()) {
            if ((int) $this->user->id === (int) $bookings->member) {
                $bookings->reset();

                return true;
            }
        }

        $bookings->reset();

        return false;
    }
}
