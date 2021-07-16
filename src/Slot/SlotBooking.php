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
use Markocupic\ResourceBookingBundle\Utils\Utils;
use Symfony\Component\Security\Core\Security;

/**
 * Class SlotBooking.
 *
 * Use SlotFactory to create Slot instance
 */
class SlotBooking
{
    /**
     * @var ContaoFramework
     */
    private $framework;
    /**
     * @var Security
     */
    private $security;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var ResourceBookingResourceModel
     */
    private $resource;

    /**
     * @var int
     */
    private $startTime;

    /**
     * @var int
     */
    private $endTime;

    /**
     * @var int
     */
    private $desiredItems;

    /**
     * @var ?Collection
     */
    private $bookings;

    /**
     * @var MemberModel|null
     */
    private $user;

    public function __construct(ContaoFramework $framework, Security $security, Utils $utils)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->utils = $utils;
    }

    public function create(ResourceBookingResourceModel $resource, int $startTime, int $endTime, int $desiredItems = 1)
    {
        $this->resource = $resource;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->desiredItems = $desiredItems;

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

    /**
     * Check, if slot is bookable.
     */
    public function isBookable(): bool
    {
        if ($this->endTime < time()) {
            return false;
        }

        $itemsBooked = 0;

        if (null !== ($objBookings = $this->getBookings())) {
            while ($objBookings->next()) {
                if ($this->user && (int) $this->user->id === (int) $objBookings->member) {
                    continue;
                }
                $itemsBooked += (int) $objBookings->itemsBooked;
            }
        }

        if ((int) $this->resource->itemsAvailable > $itemsBooked) {
            return true;
        }

        return false;
    }

    /**
     * Check, if slot is bookable, independent of a logged in user.
     */
    public function isCancelable(): bool
    {
        $bookings = $this->getBookings();

        if ($this->endTime < time()) {
            return false;
        }

        if (null === $bookings) {
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

    public function getBookings(): ?Collection
    {
        if (null !== $this->bookings) {
            $this->bookings->reset();
        }

        return $this->bookings;
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

    public function getRepetitionStop(): int
    {
        return $this->repetitionStop;
    }

    private function setBookings(): void
    {
        /** @var ResourceBookingModel $resourceBookingModelAdapter */
        $resourceBookingModelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);

        $this->bookings = $resourceBookingModelAdapter
            ->findByResourceStarttimeAndEndtime($this->resource, $this->startTime, $this->endTime)
        ;
    }
}
