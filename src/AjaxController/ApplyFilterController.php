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
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;

final class ApplyFilterController extends AbstractController implements ControllerInterface
{
    use RefreshDataTrait;

    public const REQUEST_NAME = 'applyFilterRequest';

    private EventDispatcherInterface $eventDispatcher;

    private WeekBoundaries $weekBoundaries;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    #[Required]
    public function setWeekBoundaries(WeekBoundaries $weekBoundaries): void
    {
        $this->weekBoundaries = $weekBoundaries;
    }

    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        // Update the session with request data
        $this->applyResourceTypeFromRequest($request);
        $this->applyResourceFromRequest($request);
        $this->updatePermittedWeeks();
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

    /**
     * The first and last permitted weeks depend on the settings of the
     * active resource and have therefore to be updated whenever the resource changes.
     *
     * @throws \Exception
     */
    private function updatePermittedWeeks(): void
    {
        $intRes = (int) $this->sessionBag->get('res', 0);
        $activeResource = $intRes > 0 ? $this->framework->getAdapter(ResourceBookingResourceModel::class)->findById($intRes) : null;

        $this->weekBoundaries->storePermittedWeeks($this->sessionBag, $this->utils->getAppConfig(), $activeResource, time());
    }

    private function applyActiveWeekFromRequest(Request $request): void
    {
        $appConfig = $this->utils->getAppConfig();
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        $intTstampDate = (int) $request->request->get('date', 0);

        // The date must be the first day of a week; otherwise fall back to the current week.
        if ($intTstampDate <= 0 || $dateHelperAdapter->getFirstDayOfWeek($appConfig, $intTstampDate) !== $intTstampDate) {
            $intTstampDate = $dateHelperAdapter->getFirstDayOfCurrentWeek($appConfig);
        }

        // Clamp the date to the permitted weeks of the active resource.
        $intTstampDate = max($intTstampDate, (int) $this->sessionBag->get('tstampFirstPermittedWeek'));
        $intTstampDate = min($intTstampDate, (int) $this->sessionBag->get('tstampLastPermittedWeek'));

        $this->sessionBag->set('activeWeekTstamp', $intTstampDate);
    }
}
