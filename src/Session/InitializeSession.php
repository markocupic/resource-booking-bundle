<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Session;

use Contao\Config;
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

        // Add session id to the session & validate session id when is ajax request
        if (!$environmentAdapter->get('isAjaxRequest'))
        {
            $this->sessionBag->set('sessionId', $request->query->get('sessionId'));
        }
        else
        {
            if ($this->sessionBag->get('sessionId') !== $request->query->get('sessionId'))
            {
                throw new \Exception('Invalid session id detected.');
            }
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
            $this->sessionBag->set('language', $language);
        }

        /** @var FrontendUser $user */
        $objUser = $this->security->getUser();
        if (!$objUser instanceof FrontendUser)
        {
            // Return empty string if user has not logged in as a frontend user
            throw new \Exception('Application is permited to frontend users only.');
        }

        // Catch resource type from session
        $intResType = ($this->sessionBag->has('resType') && $this->sessionBag->get('resType') > 0) ? $this->sessionBag->get('resType') : null;

        // Catch resource from session
        $intRes = ($this->sessionBag->has('res') && $this->sessionBag->get('res') > 0) ? $this->sessionBag->get('res') : null;

        // Catch date from session
        $intTstampDate = ($this->sessionBag->has('date')) ? $this->sessionBag->get('date') : $dateHelperAdapter->getMondayOfCurrentWeek();

        // Get resource from post
        $intResType = $request->request->has('resType') ? (int) $request->request->get('resType') : $intResType;
        if (null === $resourceBookingResourceTypeModelAdapter->findByPk($intResType))
        {
            // Set $intResType to 0,
            // if we found no valid resource neither in the session nor in the post
            $intResType = 0;
        }

        // Get resource from post
        $intRes = $request->request->has('res') ? (int) $request->request->get('res') : $intRes;
        if (null === $resourceBookingResourceModelAdapter->findByPk($intRes))
        {
            // Set $intRes to 0,
            // if we found no valid resource neither in the session nor in the post
            $intRes = 0;
        }

        // Get intBackWeeks && intBackWeeks
        $intBackWeeks = (int) $configAdapter->get('rbb_intBackWeeks');
        $this->sessionBag->set('intBackWeeks', $intBackWeeks);
        $intAheadWeeks = (int) $configAdapter->get('rbb_intAheadWeeks');
        $this->sessionBag->set('intAheadWeeks', $intAheadWeeks);

        // Get first and last possible week tstamp
        $tstampFirstPossibleWeek = $dateHelperAdapter->addWeeksToTime($intBackWeeks, $dateHelperAdapter->getMondayOfCurrentWeek());
        $this->sessionBag->set('tstampFirstPossibleWeek', $dateHelperAdapter->addWeeksToTime($intBackWeeks, $dateHelperAdapter->getMondayOfCurrentWeek()));
        $tstampLastPossibleWeek = $dateHelperAdapter->addWeeksToTime($intAheadWeeks, $dateHelperAdapter->getMondayOfCurrentWeek());
        $this->sessionBag->set('tstampLastPossibleWeek', $dateHelperAdapter->addWeeksToTime($intAheadWeeks, $dateHelperAdapter->getMondayOfCurrentWeek()));

        // Get active week timestamp
        $intTstampDate = $request->request->has('date') ? (int) $request->request->get('date') : $intTstampDate;
        $intTstampDate = $dateHelperAdapter->isValidDate($intTstampDate) ? $intTstampDate : $dateHelperAdapter->getMondayOfCurrentWeek();

        if ($intTstampDate < $tstampFirstPossibleWeek)
        {
            $intTstampDate = $tstampFirstPossibleWeek;
        }

        if ($intTstampDate > $tstampLastPossibleWeek)
        {
            $intTstampDate = $tstampLastPossibleWeek;
        }

        $this->sessionBag->set('activeWeekTstamp', (int) $intTstampDate);

        // Store data into the session
        $this->sessionBag->set('resType', (int) $intResType);
        $this->sessionBag->set('res', (int) $intRes);
        $this->sessionBag->set('date', (int) $intTstampDate);
    }

}
