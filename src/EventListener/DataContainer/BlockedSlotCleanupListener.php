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

namespace Markocupic\ResourceBookingBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\ResourceBookingBundle\Backend\BlockedSlotCleanup;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Removes blocked slots (blocked periods in the mode "slots") that would be orphaned:
 *
 *  - deleting a resource (or its resource type) deletes its blocked periods in the mode "slots";
 *  - deleting a time slot (or its timetable) removes it from the blocked periods; a blocked
 *    period without remaining slots is kept (it may be reused later).
 *
 * The callbacks run after the checks that prevent the deletion (e.g. future bookings on a
 * time slot), therefore their priority is negative.
 */
class BlockedSlotCleanupListener
{
    public function __construct(
        private readonly BlockedSlotCleanup $cleanup,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_resource', target: 'config.ondelete', priority: -10)]
    public function onDeleteResource(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->addMessage($this->cleanup->deleteForResources([(int) $dc->id]), 0);
        }
    }

    /**
     * Contao deletes the resources of a resource type (ctable) without their ondelete callbacks.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_resource_type', target: 'config.ondelete', priority: -10)]
    public function onDeleteResourceType(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $resourceIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_resource_booking_resource WHERE pid = ?', [(int) $dc->id]);

        $this->addMessage($this->cleanup->deleteForResources(array_map('intval', $resourceIds)), 0);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot', target: 'config.ondelete', priority: -10)]
    public function onDeleteTimeSlot(DataContainer $dc): void
    {
        if ($dc->id) {
            $this->addMessage(0, $this->cleanup->removeTimeSlots([(int) $dc->id]));
        }
    }

    /**
     * Contao deletes the time slots of a timetable (ctable) without their ondelete callbacks.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_resource_booking_time_slot_type', target: 'config.ondelete', priority: -10)]
    public function onDeleteTimeSlotType(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $timeSlotIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_resource_booking_time_slot WHERE pid = ?', [(int) $dc->id]);
        $this->addMessage(0, $this->cleanup->removeTimeSlots(array_map('intval', $timeSlotIds)));
    }

    private function addMessage(int $deleted, int $updated): void
    {
        $message = $this->framework->getAdapter(Message::class);

        if ($deleted > 0) {
            $message->addInfo($this->translator->trans('RBB.BE.blockedSlots.periodsDeleted', [$deleted], 'contao_default'));
        }

        if ($updated > 0) {
            $message->addInfo($this->translator->trans('RBB.BE.blockedSlots.periodsUpdated', [$updated], 'contao_default'));
        }
    }
}
