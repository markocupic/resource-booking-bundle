<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\ResourceBookingResourceModel;
use Contao\ResourceBookingResourceTypeModel;
use Contao\Template;
use Markocupic\ResourceBookingBundle\AjaxHandler;
use Markocupic\ResourceBookingBundle\DateHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class ResourceBookingWeekcalendarController
 * @package Markocupic\ResourceBookingBundle\Controller\FrontendModule
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    /** @var Security */
    private $security;

    /** @var SessionInterface $session */
    private $session;

    /** @var string */
    private $bagName;

    /**
     * @var ModuleModel
     */
    public $model;

    /**
     * @var FrontendUser
     */
    public $objUser;

    /**
     * @var ResourceBookingResourceTypeModel
     */
    public $objSelectedResourceType;

    /**
     * @var ResourceBookingResourceModel
     */
    public $objSelectedResource;

    /**
     * @var int
     */
    public $activeWeekTstamp;

    /**
     * @var int
     */
    public $intBackWeeks;

    /**
     * @var int
     */
    public $intAheadWeeks;

    /**
     * @var int
     */
    public $tstampFirstPossibleWeek;

    /**
     * @var int
     */
    public $tstampLastPossibleWeek;

    /**
     * ResourceBookingWeekcalendarController constructor.
     * @param Security $security
     * @param SessionInterface $session
     * @param string $bagName
     */
    public function __construct(Security $security, SessionInterface $session, string $bagName)
    {
        $this->security = $security;
        $this->session = $session;
        $this->bagName = $bagName;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request))
        {
            $this->model = $model;

            /** @var Controller $controllerAdapter */
            $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

            /** @var Environment $environmentAdapter */
            $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

            /** @var DateHelper $dateHelperAdapter */
            $dateHelperAdapter = $this->get('contao.framework')->getAdapter(DateHelper::class);

            /** @var Config $configAdapter */
            $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

            /** @var ResourceBookingResourceTypeModel $resourceBookingResourceTypeModelAdapter */
            $resourceBookingResourceTypeModelAdapter = $this->get('contao.framework')->getAdapter(ResourceBookingResourceTypeModel::class);

            /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
            $resourceBookingResourceModelAdapter = $this->get('contao.framework')->getAdapter(ResourceBookingResourceModel::class);

            /** @var FrontendUser $user */
            $objUser = $this->security->getUser();
            if (!$objUser instanceof FrontendUser)
            {
                if ($request->query->has('date') || $request->query->has('resType') || $request->query->has('res'))
                {
                    $url = \Haste\Util\Url::removeQueryString(['date', 'resType', 'res'], $environmentAdapter->get('request'));
                    $controllerAdapter->redirect($url);
                }
                // Return empty string if user has not logged in as a frontend user
                return new Response('', Response::HTTP_NO_CONTENT);
            }
            else
            {
                $this->objUser = $objUser;
            }

            /** @var \Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag $session */
            $session = $this->session->getBag($this->bagName);

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

            // Handle ajax requests
            if ($environmentAdapter->get('isAjaxRequest') && $request->request->has('action') && !empty($request->request->get('action')))
            {
                $action = $request->request->get('action');
                $objXhr = new AjaxHandler();
                if (is_callable([$objXhr, $action]))
                {
                    $objXhr->{$action}($this);
                }
                exit;
            }
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;
        $services['contao.routing.scope_matcher'] = ScopeMatcher::class;
        $services['security.helper'] = Security::class;
        $services['translator'] = TranslatorInterface::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

}
