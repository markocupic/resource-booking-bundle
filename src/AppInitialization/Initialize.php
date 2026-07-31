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

namespace Markocupic\ResourceBookingBundle\AppInitialization;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\Date;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Initialize
{
    private SessionBagInterface|null $sessionBag = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Utils $utils,
        #[Autowire('%markocupic_resource_booking.session.attribute_bag_name%')]
        private readonly string $bagName,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function initialize(int $moduleModelId, int $pageModelId): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('The application cannot be initialized without a request.');
        }

        $sessionBag = $this->getSessionBag();

        $this->guardModuleKeyIsSet();

        $moduleModel = $this->findModuleModelOrThrow($moduleModelId);
        $sessionBag->set('moduleModelId', $moduleModel->id);

        $pageModel = $this->findPageModelOrThrow($pageModelId);
        $sessionBag->set('pageModelId', $pageModel->id);

        // Apply resType/res filters coming in as url params and redirect once applied.
        if ($this->applyResourceFiltersFromRequest($request, $sessionBag)) {
            $this->redirectAfterFilterChange($request);
        }

        // Get resource type IDS from module settings.
        $arrResTypeIds = $this->framework->getAdapter(StringUtil::class)->deserialize($moduleModel->resourceBooking_resourceTypes, true);

        $resTypeId = $this->resolveAndGuardActiveResType($request, $sessionBag, $arrResTypeIds);
        $this->resolveAndGuardActiveRes($request, $sessionBag, $resTypeId);

        $arrAppConfig = $this->utils->getAppConfig();

        $this->initWeekBoundaries($sessionBag, $arrAppConfig, $moduleModel);
        $this->initLanguage($request, $sessionBag);
    }

    protected function getSessionBag(): SessionBagInterface
    {
        if (null === $this->sessionBag) {
            $request = $this->requestStack->getCurrentRequest();

            if (null === $request) {
                throw new \LogicException('The session bag cannot be resolved without a request.');
            }

            $this->sessionBag = $request->getSession()->getBag($this->bagName);
        }

        return $this->sessionBag;
    }

    protected function getCurrentTime(): int
    {
        return time();
    }

    /**
     * @throws \Exception
     */
    private function guardModuleKeyIsSet(): void
    {
        $moduleKeyAdapter = $this->framework->getAdapter(ModuleKey::class);

        if (null === $moduleKeyAdapter->getModuleKey()) {
            throw new \Exception('Module key not set.');
        }
    }

    /**
     * @throws \Exception
     */
    private function findModuleModelOrThrow(int $moduleModelId): ModuleModel
    {
        $moduleModel = $this->framework->getAdapter(ModuleModel::class)->findById($moduleModelId);

        if (null === $moduleModel) {
            throw new \Exception('Module id not set.');
        }

        return $moduleModel;
    }

    /**
     * @throws \Exception
     */
    private function findPageModelOrThrow(int $pageModelId): PageModel
    {
        $pageModel = $this->framework->getAdapter(PageModel::class)->findById($pageModelId);

        if (null === $pageModel) {
            throw new \Exception('Page model not set.');
        }

        return $pageModel;
    }

    /**
     * Reads the resType/res url params, stores them in the session, and returns
     * whether a redirect is required.
     */
    private function applyResourceFiltersFromRequest(Request $request, SessionBagInterface $sessionBag): bool
    {
        $blnRedirect = false;

        // Set resType by url param.
        if ($request->query->has('resType')) {
            $sessionBag->set('resType', $request->query->get('resType', 0));
            $blnRedirect = true;
        }

        // Set res by url param.
        if ($request->query->has('res')) {
            $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);
            $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

            // @ Todo Ermitteln ob res im erlaubten resType liegt (Modul Einstellung)
            $objRes = $resourceBookingResourceModelAdapter->findById($request->query->get('res', 0));

            if (null !== $objRes) {
                if (null !== ($objResType = $resourceBookingResourceTypeModelAdapter->findPublishedByPk((int) $objRes->pid))) {
                    $sessionBag->set('res', (int) $request->query->get('res', 0));
                    $sessionBag->set('resType', (int) $objResType->id);
                }
            }
            $blnRedirect = true;
        }

        return $blnRedirect;
    }

    private function redirectAfterFilterChange(Request $request): void
    {
        // @ Todo Datum Implementation
        // $request->query->remove('date');
        $request->query->remove('resType');
        $request->query->remove('res');
        $request->overrideGlobals();

        $this->framework->getAdapter(Controller::class)->redirect($request->getUri());
    }

    /**
     * Validates access to the active resource type and, if none is selected,
     * auto-selects it when the filter menu only offers a single one.
     *
     * @throws UnauthorizedHttpException
     */
    private function resolveAndGuardActiveResType(Request $request, SessionBagInterface $sessionBag, array $arrResTypeIds): int
    {
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        // Check if access to active resource type is allowed.
        if (($resTypeId = $sessionBag->get('resType', 0)) > 0) {
            $blnForbidden = false;

            if (null === $resourceBookingResourceTypeModelAdapter->findPublishedByPk((int) $resTypeId)) {
                $blnForbidden = true;
            }

            if (!\in_array($resTypeId, $arrResTypeIds, false)) {
                $blnForbidden = true;
            }

            if ($blnForbidden) {
                throw new UnauthorizedHttpException(\sprintf('Unauthorized access to resource type with ID %s.', $resTypeId));
            }
        } else {
            // Auto-redirect if there is only one resource type in the filter menu.
            if (!$request->isXmlHttpRequest()) {
                $oResType = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrResTypeIds);

                if (null !== $oResType && 1 === $oResType->count()) {
                    $resTypeId = $oResType->id;
                    $sessionBag->set('resType', $oResType->id);
                }
            }
        }

        return (int) $resTypeId;
    }

    /**
     * Validates access to the active resource and, if none is selected,
     * auto-selects it when the resource type only offers a single one.
     *
     * @throws UnauthorizedHttpException
     */
    private function resolveAndGuardActiveRes(Request $request, SessionBagInterface $sessionBag, int $resTypeId): void
    {
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        // Check if access to active resource is allowed.
        if (($resId = $sessionBag->get('res', 0)) > 0) {
            $blnForbidden = false;

            if (null === $resourceBookingResourceModelAdapter->findPublishedByPkAndPid((int) $resId, (int) $resTypeId)) {
                $blnForbidden = true;
            }

            if ($blnForbidden) {
                throw new UnauthorizedHttpException(\sprintf('Unauthorized access to resource with ID %s.', $resId));
            }
        } else {
            // Auto-redirect if there is only one resource in the filter menu.
            if (!$request->isXmlHttpRequest() && $resTypeId > 0) {
                $oRes = $resourceBookingResourceModelAdapter->findPublishedByPid((int) $resTypeId);

                if (null !== $oRes && 1 === $oRes->count()) {
                    $sessionBag->set('res', $oRes->id);
                }
            }
        }
    }

    /**
     * Computes the active week as well as the first/last permitted week and
     * stores them (plus their formatted dates) in the session.
     */
    private function initWeekBoundaries(SessionBagInterface $sessionBag, array $arrAppConfig, ModuleModel $moduleModel): void
    {
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);

        // Set active week timestamp.
        $tstampCurrentWeek = (int) $sessionBag->get('activeWeekTstamp', $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig));
        $sessionBag->set('activeWeekTstamp', $tstampCurrentWeek);
        $sessionBag->set('activeWeekDate', $dateAdapter->parse('Y-m-d', $tstampCurrentWeek));

        // Get first and last possible week tstamp.
        $tstampFirstPermittedWeek = $dateHelperAdapter->addWeeksToTime($arrAppConfig['intBackWeeks'], $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig));
        $sessionBag->set('tstampFirstPermittedWeek', $tstampFirstPermittedWeek);
        $sessionBag->set('tstampFirstPermittedDate', $dateAdapter->parse('Y-m-d', $tstampFirstPermittedWeek));

        $intTstampLastPermittedWeek = $dateHelperAdapter->addWeeksToTime($arrAppConfig['intAheadWeeks'], $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig));

        if ($moduleModel->resourceBooking_addDateStop) {
            $intTstampStop = $dateHelperAdapter->getFirstDayOfWeek($arrAppConfig, $moduleModel->resourceBooking_dateStop);

            if ($intTstampStop < $intTstampLastPermittedWeek) {
                $intTstampLastPermittedWeek = $intTstampStop;
            }

            if ($intTstampStop < $this->getCurrentTime()) {
                $intTstampLastPermittedWeek = $dateHelperAdapter->getFirstDayOfCurrentWeek($arrAppConfig);
            }
        }

        $sessionBag->set('tstampLastPermittedWeek', $intTstampLastPermittedWeek);
        $sessionBag->set('tstampLastPermittedWeekDate', $dateAdapter->parse('Y-m-d', $intTstampLastPermittedWeek));
    }

    private function initLanguage(Request $request, SessionBagInterface $sessionBag): void
    {
        // The locale is used by Notification Center.
        $sessionBag->set('language', LocaleUtil::formatAsLanguageTag($request->getLocale()));
    }
}
