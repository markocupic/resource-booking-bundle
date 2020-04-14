<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Runtime;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\DateHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class Runtime
 * @package Markocupic\ResourceBookingBundle\Runtime
 */
class Runtime
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
    public $sessionBag;

    /** @var ModuleModel */
    public $moduleModel;

    /** @var PageModel */
    public $pageModel;

    /** @var FrontendUser */
    public $objUser;

    /** @var string */
    public $language;

    /** @var ResourceBookingResourceTypeModel */
    public $objSelectedResourceType;

    /** @var ResourceBookingResourceModel */
    public $objSelectedResource;

    /** @var int */
    public $intBackWeeks;

    /** @var int */
    public $intAheadWeeks;

    /** @var int */
    public $activeWeekTstamp;

    /** @var int */
    public $tstampFirstPossibleWeek;

    /** @var int */
    public $tstampLastPossibleWeek;

    /**
     * Runtime constructor.
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
    }

    /**
     * @param int|null $moduleModelId
     * @param int|null $pageModelId
     * @throws \Exception
     */
    public function initialize(int $moduleModelId = null, int $pageModelId = null)
    {
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var DateHelper $dateHelperAdapter */
        $dateHelperAdapter = $this->framework->getAdapter(DateHelper::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
        $resourceBookingResourceTypeModelAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
        $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag $session */
        $session = $this->session->getBag($this->bagName);

        // Store sessionBag
        $this->sessionBag = $session;

        // Add session id to the session & validate session id when is ajax request
        if (!$environmentAdapter->get('isAjaxRequest'))
        {
            $session->set('sessionId', $request->query->get('sessionId'));
        }
        else
        {
            if ($session->get('sessionId') !== $request->query->get('sessionId'))
            {
                throw new \Exception('Invalid session id detected.');
            }
        }

        // Get $moduleModelId from parameter or session
        $moduleModelId = $moduleModelId !== null ? $moduleModelId : ($session->has('moduleModelId') ? $session->get('moduleModelId') : null);

        $objModuleModel = ModuleModel::findByPk($moduleModelId);
        if ($objModuleModel === null)
        {
            throw new \Exception('Module id not set.');
        }

        $session->set('moduleModelId', $objModuleModel->id);
        $this->moduleModel = $objModuleModel;

        // Get $pageModelId from parameter or session
        $pageModelId = $pageModelId !== null ? $pageModelId : ($session->has('pageModelId') ? $session->get('pageModelId') : null);

        $objPageModel = PageModel::findByPk($pageModelId);
        if ($objPageModel === null)
        {
            throw new \Exception('Page model not set.');
        }

        $session->set('pageModelId', $objPageModel->id);
        $this->pageModel = $objPageModel;

        if (!$environmentAdapter->get('isAjaxRequest'))
        {
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
            $session->set('language', $language);
        }

        /** @var FrontendUser $user */
        $objUser = $this->security->getUser();
        if (!$objUser instanceof FrontendUser)
        {
            // Return empty string if user has not logged in as a frontend user
            throw new \Exception('Application is permited to frontend users only.');
        }
        else
        {
            $this->objUser = $objUser;
        }

        // Catch resource type from session
        $intResType = ($session->has('resType') && $session->get('resType') > 0) ? $session->get('resType') : null;

        // Catch resource from session
        $intRes = ($session->has('res') && $session->get('res') > 0) ? $session->get('res') : null;

        // Catch date from session
        $intTstampDate = ($session->has('date')) ? $session->get('date') : $dateHelperAdapter->getMondayOfCurrentWeek();

        // Get resource type from post
        $intResType = $request->request->has('resType') ? (int) $request->request->get('resType') : $intResType;
        if (empty($intResType))
        {
            // Set $intResType to 0,
            // if we found no valid resource type neither in the session nor in the post
            $intResType = 0;
        }

        // Get resource from post
        $intRes = $request->request->has('res') ? (int) $request->request->get('res') : $intRes;
        if (empty($intRes))
        {
            // Set $intRes to 0,
            // if we found no valid resource neither in the session nor in the post
            $intRes = 0;
        }

        // Get the selected resource type model
        $this->objSelectedResourceType = $resourceBookingResourceTypeModelAdapter->findPublishedByPk($intResType);

        // Get the selected resource model
        $this->objSelectedResource = $resourceBookingResourceModelAdapter->findPublishedByPkAndPid($intRes, $intResType);

        // Get intBackWeeks && intBackWeeks
        $this->intBackWeeks = (int) $configAdapter->get('rbb_intBackWeeks');
        $this->intAheadWeeks = (int) $configAdapter->get('rbb_intAheadWeeks');

        // Get first and last possible week tstamp
        $this->tstampFirstPossibleWeek = $dateHelperAdapter->addWeeksToTime($this->intBackWeeks, $dateHelperAdapter->getMondayOfCurrentWeek());
        $this->tstampLastPossibleWeek = $dateHelperAdapter->addWeeksToTime($this->intAheadWeeks, $dateHelperAdapter->getMondayOfCurrentWeek());

        // Get active week timestamp
        $intTstampDate = $request->request->has('date') ? (int) $request->request->get('date') : $intTstampDate;
        $intTstampDate = $dateHelperAdapter->isValidDate($intTstampDate) ? $intTstampDate : $dateHelperAdapter->getMondayOfCurrentWeek();

        if ($intTstampDate < $this->tstampFirstPossibleWeek)
        {
            $intTstampDate = $this->tstampFirstPossibleWeek;
        }

        if ($intTstampDate > $this->tstampLastPossibleWeek)
        {
            $intTstampDate = $this->tstampLastPossibleWeek;
        }

        $this->activeWeekTstamp = $intTstampDate;

        // Store data into the session
        $session->set('resType', (int) $intResType);
        $session->set('res', (int) $intRes);
        $session->set('date', (int) $intTstampDate);
    }

}
