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
     * @var ResourceBookingResourceModel
     */
    protected $resource;

    /**
     * @var int
     */
    protected $startTime;

    /**
     * @var int
     */
    protected $endTime;

    /**
     * @var int
     */
    protected $desiredItems;

    /**
     * @var ?Collection
     */
    protected $bookings;

    /**
     * @var MemberModel|null
     */
    protected $user;

    /**
     * @var int|null
     */
    protected $bookingRepeatStopWeekTstamp;

    public function __construct(ContaoFramework $framework, Security $security, Utils $utils)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->utils = $utils;
    }

    public function create(ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1, int $bookingRepeatStopWeekTstamp = null): SlotInterface
    {
        $this->resource = $resource;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->desiredItems = $desiredItems;

        if (null === $bookingRepeatStopWeekTstamp) {
            $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
            $bookingRepeatStopWeekTstamp = $dateHelperAdapter->getMondayOfCurrentWeek($this->startTime);
        }

        // This is the timestamp of a monday,
        // All bookings in the current week are allowed
        $this->bookingRepeatStopWeekTstamp = $bookingRepeatStopWeekTstamp;

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
        return null !== $this->bookings;
    }

    public function getBookings(): ?Collection
    {
        if (null !== $this->bookings) {
            $this->bookings->reset();
        }

        return $this->bookings;
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
        return $this->endTime;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getDesiredItems(): int
    {
        return $this->desiredItems;
    }

    public function getResource(): ?ResourceBookingResourceModel
    {
        return $this->resource;
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
        if ($this->endTime < time()) {
            return false;
        }

        if ($this->startTime > strtotime('+1 week', $this->bookingRepeatStopWeekTstamp)) {
            return false;
        }

        if ($this->utils->getModuleModel()->resourceBooking_addDateStop) {
            if ($this->endTime > $this->utils->getModuleModel()->resourceBooking_dateStop + 24 * 3600) {
                return false;
            }
        }

        return true;
    }

    protected function setBookings(): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        $this->bookings = $resourceBookingModelAdapter
            ->findByResourceStarttimeAndEndtime($this->resource, $this->startTime, $this->endTime)
        ;
    }
}
