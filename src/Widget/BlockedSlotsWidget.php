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

namespace Markocupic\ResourceBookingBundle\Widget;

use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\Message;
use Contao\System;
use Contao\Widget;
use Markocupic\ResourceBookingBundle\Backend\BlockedSlotCalendar;
use Markocupic\ResourceBookingBundle\Controller\Backend\BlockedSlotsController;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Util\BlockedSlots;

/**
 * Calendar view to select single blocked slots of a resource
 * (tl_resource_booking_blocked_period.blockedSlots, inputType "rbbBlockedSlots").
 *
 * The week view is loaded from BlockedSlotsController (assets/backend.js). The
 * selection is kept in a hidden input as "<timeSlot>:<Y-m-d>,…",
 * so that slots of other weeks are kept while browsing.
 */
class BlockedSlotsWidget extends Widget
{
    protected $blnSubmitInput = true;

    protected $strTemplate = 'be_widget';

    public function generate(): string
    {
        $container = System::getContainer();
        $translator = $container->get('translator');

        $slots = BlockedSlots::fromPeriod(['blockedSlots' => $this->varValue]);
        $resourceId = $this->getResourceId();

        $hiddenInput = \sprintf(
            '<input type="hidden" name="%s" id="ctrl_%s" value="%s">',
            $this->strName,
            $this->strId,
            BlockedSlots::toString($slots),
        );

        if ($resourceId < 1) {
            return $hiddenInput.'<p class="tl_info">'.$translator->trans('RBB.BE.blockedSlots.selectResource', [], 'contao_default').'</p>';
        }

        // Orphaned blocked period: the resource has been deleted
        if (null === ($resource = ResourceBookingResourceModel::findById($resourceId))) {
            return $hiddenInput.'<p class="tl_error">'.$translator->trans('RBB.BE.blockedSlots.resourceMissing', [], 'contao_default').'</p>';
        }

        // Start with the week of the next blocked slot
        $week = time();

        foreach ($slots as $slot) {
            if ($slot['date'] >= date('Y-m-d')) {
                $week = (int) strtotime($slot['date']);
                break;
            }
        }

        $labels = [];

        foreach (['prevWeek', 'nextWeek', 'today', 'loading', 'loadError', 'noTimeSlots', 'selected', 'bookings', 'blockedBy', 'past', 'warningBookings', 'showWeek', 'selectedCount', 'showList', 'listTitle', 'ownSlots', 'otherSlots', 'noOwnSlots', 'remove', 'close', 'unknownTimeSlot', 'unpublished', 'removedHint', 'colDate', 'colTime', 'colBookings', 'colPeriod', 'calendarWeek'] as $key) {
            $labels[$key] = $translator->trans('RBB.BE.blockedSlots.'.$key, [], 'contao_default');
        }

        $calendar = $container->get(BlockedSlotCalendar::class);

        // Blocked slots with bookings (also outside the displayed week), see the warning in backend.js
        $slotsWithBookings = $calendar->getSlotsWithBookings($resource, $slots);

        // List of all blocked slots (modal): labels of the time slots and the slots of the other blocked periods
        $listData = [
            'timeSlots' => (object) $calendar->getTimeSlotLabels($resource),
            'otherSlots' => $calendar->getOtherBlockedSlots($resource, (int) ($this->getCurrentRecord()['id'] ?? 0)),
            'locale' => str_replace('_', '-', (string) ($GLOBALS['TL_LANGUAGE'] ?? 'de')),
            // Contao icon of the delete operation
            'deleteIcon' => Image::getPath('delete.svg'),
        ];

        $GLOBALS['TL_JAVASCRIPT']['rbb_backend'] = $container->get('assets.packages')->getUrl('js/backend.js', 'markocupic_resource_booking');

        return \sprintf(
            '<div class="rbb-blocked-slots" id="rbb_blocked_slots_%s" data-input="ctrl_%s" data-url="%s" data-resource="%d" data-period="%d" data-week="%d" data-labels="%s" data-slots-with-bookings="%s" data-list="%s">%s<div class="rbb-blocked-slots__calendar" aria-live="polite"></div></div>'
            .'<script>window.RbbBlockedSlots && window.RbbBlockedSlots.initAll();</script>',
            $this->strId,
            $this->strId,
            htmlspecialchars($container->get('router')->generate(BlockedSlotsController::class), ENT_QUOTES),
            $resourceId,
            (int) ($this->getCurrentRecord()['id'] ?? 0),
            $week,
            htmlspecialchars(json_encode($labels, JSON_THROW_ON_ERROR), ENT_QUOTES),
            htmlspecialchars(json_encode((object) $slotsWithBookings, JSON_THROW_ON_ERROR), ENT_QUOTES),
            htmlspecialchars(json_encode($listData, JSON_THROW_ON_ERROR), ENT_QUOTES),
            $hiddenInput,
        );
    }

    /**
     * @return list<array{timeSlot: int, date: string}>|string
     */
    protected function validator($varInput): mixed
    {
        $slots = BlockedSlots::parse(trim((string) $varInput));

        if (empty($slots)) {
            // Handles mandatory fields
            return parent::validator('');
        }

        $resource = ResourceBookingResourceModel::findById($this->getResourceId());

        if (null !== $resource) {
            $valid = System::getContainer()->get(BlockedSlotCalendar::class)->filterValidSlots($resource, $slots);

            // E.g. after the timetable of the resource has been changed
            if ($removed = \count($slots) - \count($valid)) {
                Message::addInfo(System::getContainer()->get('translator')->trans('RBB.BE.blockedSlots.invalidSlotsRemoved', [$removed], 'contao_default'));
            }

            $slots = $valid;
        }

        if (empty($slots)) {
            return parent::validator('');
        }

        return $slots;
    }

    /**
     * The selected resource: the submitted value or the value of the record.
     */
    private function getResourceId(): int
    {
        if (null !== ($posted = Input::post('resource')) && '' !== $posted) {
            return (int) $posted;
        }

        return (int) ($this->getCurrentRecord()['resource'] ?? 0);
    }

    /**
     * The record being edited (Contao 5: DataContainer::getCurrentRecord()).
     *
     * @return array<string, mixed>
     */
    private function getCurrentRecord(): array
    {
        $dc = $this->dataContainer;

        if (!$dc instanceof DataContainer || !$dc->id) {
            return [];
        }

        return $dc->getCurrentRecord() ?? [];
    }
}
