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

namespace Markocupic\ResourceBookingBundle\Booking;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Model\Collection;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Exception\FieldValidationException;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Restriction\ResourceRules;
use Markocupic\ResourceBookingBundle\Slot\SlotCollection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Loads, saves and deletes the bookings (tl_resource_booking) of the booking and
 * cancellation requests. The bookings are Contao models: the events of the booking
 * process (PreBookingEvent, PostBookingEvent, ...) pass them to the listeners.
 *
 * The transaction is handled by the caller (see BookingController, CancelController).
 */
class BookingRepository
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * Locks the resource record until the transaction is committed: all bookings of
     * the same resource are serialized. Otherwise two members booking the same slot
     * at the same time could both pass the availability check (double booking).
     *
     * Call it before the first read of the transaction (consistent snapshot).
     */
    public function lockResource(int $resourceId): void
    {
        $this->connection->executeQuery('SELECT id FROM tl_resource_booking_resource WHERE id = ? FOR UPDATE', [$resourceId]);
    }

    /**
     * Creates the (not yet saved) bookings of the slots. An existing booking of the
     * logged-in member in a slot is updated instead of creating a new one.
     *
     * @return Collection<ResourceBookingModel>|null
     */
    public function createBookings(SlotCollection $slots, ResourceBookingResourceModel $resource): Collection|null
    {
        $modelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);
        $confirmed = ResourceRules::fromModel($resource)->autoConfirm;
        $now = $this->clock->now()->getTimestamp();
        $bookings = [];

        foreach ($slots as $slot) {
            $memberBooking = $slot->getMemberBooking();
            $booking = null === $memberBooking ? new ResourceBookingModel() : $modelAdapter->findById($memberBooking['id']);

            if (null === $booking) {
                continue;
            }

            foreach ($slot->bookingData as $key => $value) {
                if ('id' === $key && empty($value)) {
                    continue;
                }

                $booking->{$key} = $value;
            }

            $booking->confirmed = $confirmed;
            $booking->tstamp = $now;
            $booking->bookingTime = $now;

            $bookings[] = $booking;
        }

        return [] === $bookings ? null : new Collection($bookings, 'tl_resource_booking');
    }

    /**
     * Saves the bookings.
     *
     * @param Collection<ResourceBookingModel> $bookings
     *
     * @throws FieldValidationException if a mandatory field (DCA) is empty
     */
    public function save(Collection $bookings, ResourceBookingResourceModel $resource): void
    {
        $bookings->reset();

        while ($bookings->next()) {
            $booking = $bookings->current();

            if (null !== ($field = $this->findEmptyMandatoryField($booking->row()))) {
                $label = $GLOBALS['TL_LANG']['tl_resource_booking'][$field][0] ?? $field;

                throw FieldValidationException::forField($field, $this->translator->trans('RBB.ERR.mandatoryFieldNotFilledIn', [$label], 'contao_default'));
            }

            $booking->save();

            $this->contaoGeneralLogger?->info(\sprintf('New resource "%s" (with ID %s) has been booked.', $resource->title, $booking->id));
        }

        $bookings->reset();
    }

    /**
     * @return Collection<ResourceBookingModel>|null
     */
    public function findByBookingUuid(string $bookingUuid): Collection|null
    {
        return $this->framework->getAdapter(ResourceBookingModel::class)->findByBookingUuid($bookingUuid);
    }

    public function findById(int $id): ResourceBookingModel|null
    {
        return $this->framework->getAdapter(ResourceBookingModel::class)->findById($id);
    }

    /**
     * The booking and, if requested, its future repetitions: bookings of the member
     * with the same booking UUID and time slot on the same weekday. Past repetitions
     * of the series are kept.
     *
     * @return Collection<ResourceBookingModel>|null
     */
    public function findBookingsToCancel(ResourceBookingModel $booking, int $memberId, bool $withRepetitions): Collection|null
    {
        $modelAdapter = $this->framework->getAdapter(ResourceBookingModel::class);
        $ids = [(int) $booking->id];

        if ($withRepetitions) {
            $dateAdapter = $this->framework->getAdapter(Date::class);
            $weekday = $dateAdapter->parse('D', $booking->startTime);

            $repetitions = $modelAdapter->findBy(
                [
                    'tl_resource_booking.bookingUuid=?',
                    'tl_resource_booking.timeSlotId=?',
                    'tl_resource_booking.id!=?',
                    'tl_resource_booking.member=?',
                    'tl_resource_booking.endTime>=?',
                ],
                [$booking->bookingUuid, $booking->timeSlotId, $booking->id, $memberId, $this->clock->now()->getTimestamp()],
            );

            if (null !== $repetitions) {
                while ($repetitions->next()) {
                    if ($dateAdapter->parse('D', $repetitions->startTime) === $weekday) {
                        $ids[] = (int) $repetitions->id;
                    }
                }
            }
        }

        return $modelAdapter->findByIds($ids);
    }

    /**
     * Deletes the bookings and returns their IDs (Model::delete() removes the ID from the model).
     *
     * @param Collection<ResourceBookingModel> $bookings
     *
     * @return list<int>
     */
    public function delete(Collection $bookings): array
    {
        $deletedIds = [];
        $bookings->reset();

        while ($bookings->next()) {
            $booking = $bookings->current();
            $id = (int) $booking->id;
            $resource = $booking->getRelated('pid');

            if ($booking->delete()) {
                $deletedIds[] = $id;

                $this->contaoGeneralLogger?->info(\sprintf(
                    'Resource Booking for "%s" (with ID %s) has been deleted.',
                    $resource instanceof ResourceBookingResourceModel ? $resource->title : '',
                    $id,
                ));
            }
        }

        $bookings->reset();

        return $deletedIds;
    }

    /**
     * Returns the first mandatory field (DCA eval.mandatory) of tl_resource_booking
     * that is empty, or null.
     *
     * @param array<string, mixed> $row
     */
    private function findEmptyMandatoryField(array $row): string|null
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer('tl_resource_booking');

        foreach ($GLOBALS['TL_DCA']['tl_resource_booking']['fields'] ?? [] as $field => $config) {
            if (true === ($config['eval']['mandatory'] ?? null) && empty($row[$field])) {
                return (string) $field;
            }
        }

        return null;
    }
}
