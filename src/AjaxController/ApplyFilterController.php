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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\Traits\RefreshDataTrait;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

final class ApplyFilterController extends AbstractController implements ControllerInterface
{
    use RefreshDataTrait;

    public const REQUEST_NAME = 'applyFilterRequest';

    private EventDispatcherInterface $eventDispatcher;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        // Update the session with request data
        $this->applyResourceTypeFromRequest($request);
        $this->applyResourceFromRequest($request);
        $this->applyActiveWeekFromRequest($request);

        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->getRefreshedData($ajaxResponse));

        return $ajaxResponse;
    }

    private function applyResourceTypeFromRequest(Request $request): void
    {
        $intResType = (int) $request->request->get('resType', 0);
        $isValid = null !== $this->framework->getAdapter(ResourceBookingResourceTypeModel::class)->findById($intResType);

        $this->sessionBag->set('resType', $isValid ? $intResType : 0);
    }

    private function applyResourceFromRequest(Request $request): void
    {
        if (0 === $this->sessionBag->get('resType')) {
            $this->sessionBag->set('res', 0);

            return;
        }

        $intResType = $this->sessionBag->get('resType');
        $intRes = (int) $request->request->get('res', 0);
        $objRes = $this->framework->getAdapter(ResourceBookingResourceModel::class)->findById($intRes);

        $isValid = null !== $objRes && $objRes->pid === $intResType;

        $this->sessionBag->set('res', $isValid ? $intRes : 0);
    }

    private function applyActiveWeekFromRequest(Request $request): void
    {
        $appConfig = $this->utils->getAppConfig();
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $intTstampDate = (int) $request->request->get('date', 0);

        if (!$dateHelperAdapter->isWithinAllowedDateRange($intTstampDate, $appConfig)) {
            $intTstampDate = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig);
        }

        $intTstampDate = max($intTstampDate, (int) $this->sessionBag->get('tstampFirstPermittedWeek'));
        $intTstampDate = min($intTstampDate, (int) $this->sessionBag->get('tstampLastPermittedWeek'));

        $this->sessionBag->set('activeWeekTstamp', $intTstampDate);
    }
}
