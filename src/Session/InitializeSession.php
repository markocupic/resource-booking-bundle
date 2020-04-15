<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Session;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\ResourceBookingResourceModel;
use Contao\Controller;
use Contao\ResourceBookingResourceTypeModel;
use Contao\StringUtil;
use Contao\Environment;
use Haste\Util\Url;
use Markocupic\ResourceBookingBundle\DateHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * Class InitializeSession
 * @package Markocupic\ResourceBookingBundle\Session
 */
class InitializeSession
{
    /** @var ContaoFramework */
    private $framework;

    /** @var Security */
    private $security;

    /** @var SessionInterface */
    private $session;

    /** @var RequestStack */
    private $requestStack;

    /** @var string */
    private $bagName;

    /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag */
    private $sessionBag;

    /**
     * InitializeSession constructor.
     * @param ContaoFramework $framework
     * @param Security $security
     * @param SessionInterface $session
     * @param RequestStack $requestStack
     * @param string $bagName
     */
    public function __construct(ContaoFramework $framework, Security $security, SessionInterface $session, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->session = $session;
        $this->requestStack = $requestStack;
        $this->bagName = $bagName;
        $this->sessionBag = $session->getBag($bagName);
    }

    /**
     * @param bool $isAjaxRequest
     * @param int|null $moduleModelId
     * @param int|null $pageModelId
     * @throws \Exception
     */
    public function initialize(bool $isAjaxRequest, ?int $moduleModelId, ?int $pageModelId)
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

        /** @var \Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var FrontendUser $user */
        $objUser = null;
        if ($this->security->getUser() instanceof FrontendUser)
        {
            $objUser = $this->security->getUser();
        }

        // Validate session id against cookie an frontend user password (if user has logged in)
        $blnForbidden = true;
        $pw = $objUser ? $objUser->getPassword() : '';
        if (!$isAjaxRequest)
        {
            if (
                strlen($request->query->get('sessionId')) &&
                sha1($request->cookies->get('_contao_resource_booking_token') . $pw) === $request->query->get('sessionId')
            )
            {
                //Add session id to the session bag
                $this->sessionBag->set('sessionId', $request->query->get('sessionId'));
                $blnForbidden = false;
            }
        }
        else
        {
            if ($this->sessionBag->get('sessionId') === $request->query->get('sessionId') && sha1($request->cookies->get('_contao_resource_booking_token') . $pw) === $request->query->get('sessionId'))
            {
                $blnForbidden = false;
            }
        }

        if ($blnForbidden === true)
        {
            throw new UnauthorizedHttpException('Invalid session detected. Please check your cookie settings.');
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
        if (!$isAjaxRequest)
        {
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
        }

        // Set resType by url param
        if ($request->query->has('resType'))
        {
            $this->sessionBag->set('resType', $request->query->get('resType', 0));
            $blnRedirect = true;
        }

        // Set res by url param
        if ($request->query->has('res'))
        {
            // @ Todo Automatisch resType ermitteln und checken ob res im erlaubten resType liegt (Modul Einstellung)
            $this->sessionBag->set('res', $request->query->get('res', 0));
            $blnRedirect = true;
        }

        if ($blnRedirect)
        {
            //@ Todo Datum Implementation
            //$url = $urlAdapter->removeQueryString(['date', 'resType', 'res'], $environmentAdapter->get('request'));
            $url = $urlAdapter->removeQueryString(['resType', 'res'], $environmentAdapter->get('request'));
            $controllerAdapter->redirect($url);
        }

        // Check if access to active resource type is allowed
        if (($resTypeId = $this->sessionBag->get('resType', 0)) > 0)
        {
            $blnForbidden = false;
            if ($resourceBookingResourceTypeModelAdapter->findPublishedByPk($resTypeId) === null)
            {
                $blnForbidden = true;
            }
            // Get ids from module settings
            $arrResTypeIds = $stringUtilAdapter->deserialize($objModuleModel->resourceBooking_resourceTypes, true);

            if (!in_array($resTypeId, $arrResTypeIds))
            {
                $blnForbidden = true;
            }

            if ($blnForbidden)
            {
                throw new UnauthorizedHttpException(sprintf('Unauthorized access to resource type with ID %s.', $resTypeId));
            }
        }

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
