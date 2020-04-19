<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Contao\CoreBundle\Csrf\MemoryTokenStorage;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Markocupic\ResourceBookingBundle\Csrf\CsrfTokenManager;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class ResourceBookingWeekcalendarController
 * @package Markocupic\ResourceBookingBundle\Controller\FrontendModule
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    /** @var ContaoFramework */
    private $framework;

    /** @var Initialize */
    private $appInitializer;

   /** @var MemoryTokenStorage  */
    private $tokenStorage;

    /** @var CsrfTokenManager */
    private $CsrfTokenManager;

    /**
     * ResourceBookingWeekcalendarController constructor.
     * @param ContaoFramework $framework
     * @param Initialize $appInitializer
     * @param MemoryTokenStorage $tokenStorage
     * @param CsrfTokenManager $csrfCookie
     */
    public function __construct(ContaoFramework $framework, Initialize $appInitializer, MemoryTokenStorage $tokenStorage, CsrfTokenManager $CsrfTokenManager)
    {
        $this->framework = $framework;
        $this->appInitializer = $appInitializer;
        $this->tokenStorage = $tokenStorage;
        $this->CsrfTokenManager = $CsrfTokenManager;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Is frontend
        if ($page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request))
        {
            /** @var Controller $controllerAdapter */
            $controllerAdapter = $this->framework->getAdapter(Controller::class);

            $container = \Contao\System::getContainer();

            if (!$this->CsrfTokenManager->hasValidCsrfToken())
            {
                // Generate csrf token that we will use as the session bag key
                $container
                    ->get('contao.csrf.token_manager')
                    ->getToken($container->getParameter('contao.csrf_token_name'))
                    ->getValue();

                // redirect
                $controllerAdapter->reload();
            }

            // Initialize application
            $this->appInitializer->initialize(false, (int) $model->id, (int) $page->id);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {

        /**
        $action = 'bsadd';
        $objJson = new \Markocupic\ResourceBookingBundle\Ajax\AjaxResponse();
        $objJson->setStatus(\Markocupic\ResourceBookingBundle\Ajax\AjaxResponse::STATUS_ERROR);
        $objJson->setErrorMessage(sprintf('Action "%s" not found.', $action));
        $data = [
            'k1' => 'bla',
            'k2' => ['a' => '323213'],
        ];
        $objJson->setDataFromArray($data);

        die(print_r($objJson->getAll(),true));
*/
        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

}
