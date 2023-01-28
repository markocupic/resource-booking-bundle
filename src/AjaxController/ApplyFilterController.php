<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\Traits\RefreshDataTrait;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Slot\SlotFactory;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ApplyFilterController extends AbstractController implements ControllerInterface
{
    use RefreshDataTrait;

    private EventDispatcherInterface $eventDispatcher;
    private SlotFactory $slotFactory;
    private TranslatorInterface $translator;

    /**
     * @required
     * Use setter via "required" annotation injection in child classes instead of __construct injection
     * see: https://stackoverflow.com/questions/58447365/correct-way-to-extend-classes-with-symfony-autowiring
     * see: https://symfony.com/doc/current/service_container/calls.html
     */
    public function _setController(EventDispatcherInterface $eventDispatcher, SlotFactory $slotFactory, TranslatorInterface $translator): void
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->slotFactory = $slotFactory;
        $this->translator = $translator;
    }

    /**
     * @throws \Exception
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
            if ((int) $objRes->pid === $intResType) {
                $this->sessionBag->set('res', $intRes);
                $invalidRes = false;
            }
        }

        // Set res to 0, if the res is invalid
        if ($invalidRes) {
            $this->sessionBag->set('res', 0);
        }

        // Get app config
        $arrAppConfig = $this->utils->getAppConfig();

        // Get active week timestamp from post request
        $intTstampDate = (int) $request->request->get('date', 0);
        $intTstampDate = $dateHelperAdapter->isDateInPermittedRange($intTstampDate, $arrAppConfig) ? $intTstampDate : $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig);

        // Validate $intTstampDate
        $tstampFirstPermittedWeek = $this->sessionBag->get('tstampFirstPermittedWeek');

        if ($intTstampDate < $tstampFirstPermittedWeek) {
            $intTstampDate = $tstampFirstPermittedWeek;
        }

        $tstampLastPermittedWeek = $this->sessionBag->get('tstampLastPermittedWeek');

        if ($intTstampDate > $tstampLastPermittedWeek) {
            $intTstampDate = $tstampLastPermittedWeek;
        }

        $this->sessionBag->set('activeWeekTstamp', (int) $intTstampDate);

        // Fetch refreshed data and send it to the browser
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->getRefreshedData());
    }
}
