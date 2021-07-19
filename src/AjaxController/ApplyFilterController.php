<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Exception;
use Markocupic\ResourceBookingBundle\Booking\BookingMain;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ApplyFilterController.
 */
final class ApplyFilterController extends AbstractController implements ControllerInterface
{
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(EventDispatcherInterface $eventDispatcher, BookingMain $bookingMain): void
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->bookingMain = $bookingMain;
    }

    /**
     * @throws Exception
     */
    public function generateResponse(AjaxRequestEvent $ajaxRequestEvent): void
    {
        $ajaxResponse = $ajaxRequestEvent->getAjaxResponse();

        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $request = $this->requestStack->getCurrentRequest();

        // Get resource type from post request
        $intResType = (int) $request->request->get('resType', 0);

        if (null !== $resourceBookingResourceTypeModelAdapter->findByPk($intResType)) {
            $this->sessionBag->set('resType', $intResType);
        } else {
            $this->sessionBag->set('resType', 0);
        }

        // Get resource from post request
        $intRes = (int) $request->request->get('res', 0);

        if (0 === $this->sessionBag->get('resType')) {
            // Set resource to 0, if there is no resource type selected
            $intRes = 0;
        }

        // Check if res exists
        $invalidRes = true;

        if (null !== ($objRes = $resourceBookingResourceModelAdapter->findByPk($intRes))) {
            // ... and if res is in the current resType container
            if ((int) $objRes->pid === (int) $intResType) {
                $this->sessionBag->set('res', $intRes);
                $invalidRes = false;
            }
        }

        // Set res to 0, if the res is invalid
        if ($invalidRes) {
            $this->sessionBag->set('res', 0);
        }

        // Get active week timestamp from post request
        $intTstampDate = (int) $request->request->get('date', 0);
        $intTstampDate = $dateHelperAdapter->isValidDate($intTstampDate) ? $intTstampDate : $dateHelperAdapter->getMondayOfCurrentWeek();

        // Validate $intTstampDate
        $tstampFirstPossibleWeek = $this->sessionBag->get('tstampFirstPossibleWeek');

        if ($intTstampDate < $tstampFirstPossibleWeek) {
            $intTstampDate = $tstampFirstPossibleWeek;
        }

        $tstampLastPossibleWeek = $this->sessionBag->get('tstampLastPossibleWeek');

        if ($intTstampDate > $tstampLastPossibleWeek) {
            $intTstampDate = $tstampLastPossibleWeek;
        }

        $this->sessionBag->set('activeWeekTstamp', (int) $intTstampDate);

        // Fetch data and send it to the browser
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->bookingMain->fetchData());
    }
}
