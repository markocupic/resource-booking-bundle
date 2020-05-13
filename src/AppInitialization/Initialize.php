<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AppInitialization;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Contao\StringUtil;
use Haste\Util\Url;
use Markocupic\ResourceBookingBundle\Csrf\CsrfTokenManager;
use Markocupic\ResourceBookingBundle\Date\DateHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class Initialize
 * @package Markocupic\ResourceBookingBundle\AppInitialization
 */
class Initialize
{
    /** @var ContaoFramework */
    private $framework;

    /** @var SessionInterface */
    private $session;

    /** @var RequestStack */
    private $requestStack;

    /** @var CsrfTokenManager */
    private $csrfTokenManager;

    /** @var string */
    private $bagName;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    private $sessionBag;

    /**
     * Initialize constructor.
     * @param ContaoFramework $framework
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param CsrfTokenManager $csrfTokenManager
     * @param string $bagName
     */
    public function __construct(ContaoFramework $framework, SessionInterface $session, RequestStack $requestStack, CsrfTokenManager $csrfTokenManager, string $bagName)
    {
        $this->framework = $framework;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->bagName = $bagName;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @param int $moduleModelId
     * @param int $pageModelId
     * @throws \Exception
     */
    public function initialize(int $moduleModelId, int $pageModelId)
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

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $blnForbidden = true;

        if (null !== ($strToken = $this->csrfTokenManager->getValidCsrfToken()))
        {
            //Add session id to the session bag
            $this->sessionBag->set('csrfToken', sha1($strToken));

            $blnForbidden = false;
        }

        if ($blnForbidden === true)
        {
            throw new UnauthorizedHttpException('Invalid session detected or cookie has expired. Please check your cookie settings.');
        }

        // Get $moduleModelId from parameter or session
        $moduleModelId = $moduleModelId !== null ? $moduleModelId : ($this->sessionBag->has('moduleModelId') ? $this->sessionBag->get('moduleModelId') : null);

        $objModuleModel = ModuleModel::findByPk($moduleModelId);
        if ($objModuleModel === null)
        {
            throw new \Exception('Module id not set.');
        }

        $this->sessionBag->set('moduleModelId', $objModuleModel->id);

        // Get $pageModelId from parameter or session
        $pageModelId = $pageModelId !== null ? $pageModelId : ($this->sessionBag->has('pageModelId') ? $this->sessionBag->get('pageModelId') : null);

        $objPageModel = PageModel::findByPk($pageModelId);
        if ($objPageModel === null)
        {
            throw new \Exception('Page model not set.');
        }

        $this->sessionBag->set('pageModelId', $objPageModel->id);
        $this->pageModel = $objPageModel;

        // Set language

        if (!empty($objPageModel->language))
        {
            $language = $objPageModel->language;
        }
        elseif (!empty($objPageModel->rootLanguage))
        {
            $language = $objPageModel->rootLanguage;
        }
        elseif (!empty($objPageModel->rootFallbackLanguage))
        {
            $language = $objPageModel->rootFallbackLanguage;
        }
        else
        {
            $language = 'en';
        }
        $this->sessionBag->set('language', $language);

        // Set resType by url param
        $blnRedirect = false;
        if ($request->query->has('resType'))
        {
            $this->sessionBag->set('resType', $request->query->get('resType', 0));
            $blnRedirect = true;
        }

        // Set res by url param
        if ($request->query->has('res'))
        {
            // @ Todo Ermitteln ob res im erlaubten resType liegt (Modul Einstellung)
            $objRes = $resourceBookingResourceModelAdapter->findByPk($request->query->get('res', 0));
            if ($objRes !== null)
            {
                if (($objResType = $resourceBookingResourceTypeModelAdapter->findPublishedByPk($objRes->pid)) !== null)
                {
                    $this->sessionBag->set('res', (int) $request->query->get('res', 0));
                    $this->sessionBag->set('resType', (int) $objResType->id);
                }
            }
            $blnRedirect = true;
        }

        if ($blnRedirect)
        {
            //@ Todo Datum Implementation
            //$url = $urlAdapter->removeQueryString(['date', 'resType', 'res'], $environmentAdapter->get('request'));
            $url = $urlAdapter->removeQueryString(['resType', 'res'], $environmentAdapter->get('request'));
            $controllerAdapter->redirect($url);
        }

        // Get resource type ids from module settings
        $arrResTypeIds = $stringUtilAdapter->deserialize($objModuleModel->resourceBooking_resourceTypes, true);

        // Check if access to active resource type is allowed
        if (($resTypeId = $this->sessionBag->get('resType', 0)) > 0)
        {
            $blnForbidden = false;
            if ($resourceBookingResourceTypeModelAdapter->findPublishedByPk($resTypeId) === null)
            {
                $blnForbidden = true;
            }

            if (!in_array($resTypeId, $arrResTypeIds))
            {
                $blnForbidden = true;
            }

            if ($blnForbidden)
            {
                throw new UnauthorizedHttpException(sprintf('Unauthorized access to resource type with ID %s.', $resTypeId));
            }
        }
        else
        {
            // Autoredirect if there is only one item in selection list
            if (!$environmentAdapter->get('isAjaxRequest'))
            {
                $oResType = $resourceBookingResourceTypeModelAdapter->findPublishedByIds($arrResTypeIds);
                if ($oResType !== null && $oResType->count() === 1)
                {
                    $resTypeId = $oResType->id;
                    $this->sessionBag->set('resType', $oResType->id);
                }
            }
        }

        // Get resource ids from module settings
        $arrResIds = $stringUtilAdapter->deserialize($objModuleModel->resourceBooking_resource, true);

        // Check if access to active resource is allowed
        if (($resId = $this->sessionBag->get('res', 0)) > 0)
        {
            $blnForbidden = false;
            if ($resourceBookingResourceModelAdapter->findPublishedByPkAndPid($resId, $resTypeId) === null)
            {
                $blnForbidden = true;
            }

            if ($blnForbidden)
            {
                throw new UnauthorizedHttpException(sprintf('Unauthorized access to resource with ID %s.', $resId));
            }
        }
        else
        {
            // Autoredirect if there is only one item in selection list
            if (!$environmentAdapter->get('isAjaxRequest') && $resTypeId > 0)
            {
                $oRes = $resourceBookingResourceModelAdapter->findPublishedByPid($resTypeId);
                if ($oRes !== null && $oRes->count() === 1)
                {
                    $this->sessionBag->set('res', $oRes->id);
                }
            }
        }

        // Set active week timestamp
        $tstampCurrentWeek = (int) $this->sessionBag->get('activeWeekTstamp', $dateHelperAdapter->getMondayOfCurrentWeek());
        $this->sessionBag->set('activeWeekTstamp', $tstampCurrentWeek);

        // Get intBackWeeks && intBackWeeks
        $intBackWeeks = (int) $configAdapter->get('rbb_intBackWeeks');
        $this->sessionBag->set('intBackWeeks', $intBackWeeks);
        $intAheadWeeks = (int) $configAdapter->get('rbb_intAheadWeeks');
        $this->sessionBag->set('intAheadWeeks', $intAheadWeeks);

        // Get first and last possible week tstamp
        $this->sessionBag->set('tstampFirstPossibleWeek', $dateHelperAdapter->addWeeksToTime($intBackWeeks, $dateHelperAdapter->getMondayOfCurrentWeek()));
        $this->sessionBag->set('tstampLastPossibleWeek', $dateHelperAdapter->addWeeksToTime($intAheadWeeks, $dateHelperAdapter->getMondayOfCurrentWeek()));
    }

}
