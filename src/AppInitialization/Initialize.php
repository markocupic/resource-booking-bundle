<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AppInitialization;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Haste\Util\Url;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class Initialize.
 */
class Initialize
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $bagName;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * Initialize constructor.
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->bagName = $bagName;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @throws \Exception
     */
    public function initialize(int $moduleModelId, int $pageModelId): void
    {
        /** @var ResourceBookingResourceTypeModel $environmentAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var $moduleKeyAdapter */
        $moduleKeyAdapter = $this->framework->getAdapter(ModuleKey::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        // Get $moduleModelId from parameter or session
        $moduleModelId = null !== $moduleModelId ? $moduleModelId : ($this->sessionBag->has('moduleModelId') ? $this->sessionBag->get('moduleModelId') : null);

        if (null === $moduleKeyAdapter->getModuleKey()) {
            throw new \Exception('Module key not set.');
        }

        $objModuleModel = ModuleModel::findByPk($moduleModelId);

        if (null === $objModuleModel) {
            throw new \Exception('Module id not set.');
        }

        $this->sessionBag->set('moduleModelId', $objModuleModel->id);

        // Get $pageModelId from parameter or session
        $pageModelId = null !== $pageModelId ? $pageModelId : ($this->sessionBag->has('pageModelId') ? $this->sessionBag->get('pageModelId') : null);

        $objPageModel = PageModel::findByPk($pageModelId);

        if (null === $objPageModel) {
            throw new \Exception('Page model not set.');
        }

        // Save page model id to session
        $this->sessionBag->set('pageModelId', $objPageModel->id);



        // Set resType by url param
        $blnRedirect = false;

        if ($request->query->has('resType')) {
            $this->sessionBag->set('resType', $request->query->get('resType', 0));
            $blnRedirect = true;
        }

        // Set res by url param
        if ($request->query->has('res')) {
            // @ Todo Ermitteln ob res im erlaubten resType liegt (Modul Einstellung)
            $objRes = $resourceBookingResourceModelAdapter->findByPk($request->query->get('res', 0));

            if (null !== $objRes) {
                if (null !== ($objResType = $resourceBookingResourceTypeModelAdapter->findPublishedByPk($objRes->pid))) {
                    $this->sessionBag->set('res', (int) $request->query->get('res', 0));
                    $this->sessionBag->set('resType', (int) $objResType->id);
                }
            }
            $blnRedirect = true;
        }

        if ($blnRedirect) {
            //@ Todo Datum Implementation
            //$url = $urlAdapter->removeQueryString(['date', 'resType', 'res'], $environmentAdapter->get('request'));
            $url = $urlAdapter->removeQueryString(['resType', 'res'], $environmentAdapter->get('request'));
            $controllerAdapter->redirect($url);
        }

        // Get resource type ids from module settings
        $arrResTypeIds = $stringUtilAdapter->deserialize($objModuleModel->resourceBooking_resourceTypes, true);

        // Check if access to active resource type is allowed
        if (($resTypeId = $this->sessionBag->get('resType', 0)) > 0) {
            $blnForbidden = false;

            if (null === $resourceBookingResourceTypeModelAdapter->findPublishedByPk($resTypeId)) {
                $blnForbidden = true;
            }

            if (!\in_array($resTypeId, $arrResTypeIds, false)) {
                $blnForbidden = true;
            }

            if ($blnForbidden) {
                throw new UnauthorizedHttpException(sprintf('Unauthorized access to resource type with ID %s.', $resTypeId));
            }
        } else {
            // Autoredirect if there is only one resource type in the filter menu
            if (!$environmentAdapter->get('isAjaxRequest')) {
                $oResType = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrResTypeIds);

                if (null !== $oResType && 1 === $oResType->count()) {
                    $resTypeId = $oResType->id;
                    $this->sessionBag->set('resType', $oResType->id);
                }
            }
        }

        // Check if access to active resource is allowed
        if (($resId = $this->sessionBag->get('res', 0)) > 0) {
            $blnForbidden = false;

            if (null === $resourceBookingResourceModelAdapter->findPublishedByPkAndPid($resId, $resTypeId)) {
                $blnForbidden = true;
            }

            if ($blnForbidden) {
                throw new UnauthorizedHttpException(sprintf('Unauthorized access to resource with ID %s.', $resId));
            }
        } else {
            // Autoredirect if there is only one resource in the filter menu
            if (!$environmentAdapter->get('isAjaxRequest') && $resTypeId > 0) {
                $oRes = $resourceBookingResourceModelAdapter->findPublishedByPid($resTypeId);

                if (null !== $oRes && 1 === $oRes->count()) {
                    $this->sessionBag->set('res', $oRes->id);
                }
            }
        }

        // Set active week timestamp
        $tstampCurrentWeek = (int) $this->sessionBag->get('activeWeekTstamp', $dateHelperAdapter->getMondayOfCurrentWeek());
        $this->sessionBag->set('activeWeekTstamp', $tstampCurrentWeek);

        // Overwrite rbb_intAheadWeeks from module settings
        if ((int) $objModuleModel->resourceBooking_intAheadWeek > 0) {
            $configAdapter->set('rbb_intAheadWeeks', (int) $objModuleModel->resourceBooking_intAheadWeek);
        }

        // Get intBackWeeks && intAheadWeeks
        $intBackWeeks = (int) $configAdapter->get('rbb_intBackWeeks');
        $this->sessionBag->set('intBackWeeks', $intBackWeeks);
        $intAheadWeeks = (int) $configAdapter->get('rbb_intAheadWeeks');
        $this->sessionBag->set('intAheadWeeks', $intAheadWeeks);

        // Get first and last possible week tstamp
        $this->sessionBag->set('tstampFirstPossibleWeek', $dateHelperAdapter->addWeeksToTime($intBackWeeks, $dateHelperAdapter->getMondayOfCurrentWeek()));

        $intTstampLastPossibleWeek = $dateHelperAdapter->addWeeksToTime($intAheadWeeks, $dateHelperAdapter->getMondayOfCurrentWeek());

        if ($objModuleModel->resourceBooking_addDateStop) {
            $intTstampStop = $dateHelperAdapter->getMondayOfWeekDate($objModuleModel->resourceBooking_dateStop);

            if ($intTstampStop < $intTstampLastPossibleWeek) {
                $intTstampLastPossibleWeek = $intTstampStop;
            }

            if ($intTstampStop < time()) {
                $intTstampLastPossibleWeek = $dateHelperAdapter->getMondayOfCurrentWeek();
            }
        }
        $this->sessionBag->set('tstampLastPossibleWeek', $intTstampLastPossibleWeek);
    }
}
