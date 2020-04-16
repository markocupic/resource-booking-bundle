<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Markocupic\ResourceBookingBundle\Session\InitializeSession;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class ResourceBookingWeekcalendarController
 * @package Markocupic\ResourceBookingBundle\Controller\FrontendModule
 */
class ResourceBookingWeekcalendarController extends AbstractFrontendModuleController
{
    /** @var ContaoFramework */
    private $framework;

    /** @var Security */
    private $security;

    /** @var InitializeSession */
    private $initializeSession;

    /** @var SessionInterface */
    private $session;

    /** @var string */
    private $bagName;

    /**
     * ResourceBookingWeekcalendarController constructor.
     * @param ContaoFramework $framework
     * @param Security $security
     * @param InitializeSession $initializeSession
     */
    public function __construct(ContaoFramework $framework, Security $security, InitializeSession $initializeSession, SessionInterface $session, string $bagName)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->initializeSession = $initializeSession;
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
     * @throws \Exception
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {

        // Is frontend
        if ($page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request))
        {
            /** @var Controller $controllerAdapter */
            $controllerAdapter = $this->framework->getAdapter(Controller::class);

            /** @var Environment $environmentAdapter */
            $environmentAdapter = $this->framework->getAdapter(Environment::class);

            $objUser = null;
            if ($this->security->getUser() instanceof FrontendUser)
            {
                /** @var FrontendUser $user */
                $objUser = $this->security->getUser();
            }

            // Add session id to url
            if (!$request->query->has('sessionId'))
            {
                $url = $environmentAdapter->get('request');
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $cookie = new Cookie('_contao_resource_booking_token', $token, time() + 24 * 3600);
                $response = new Response();
                $response->headers->setCookie($cookie);
                $response->send();
                $pw = $objUser ? $objUser->getPassword() : '';
                $sessId = sha1($token . $pw);
                $params = [
                    sprintf(
                        'sessionId=%s',
                        $sessId
                    ),
                ];
                $url = \Haste\Util\Url::addQueryString(implode('&', $params), $url);

                // redirect
                $controllerAdapter->redirect($url);
            }

            // Initialize application
            $this->initializeSession->initialize(false, (int) $model->id, (int) $page->id);
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
        $template->sessionId = $request->query->get('sessionId');

        // Let vue.js do the rest ;-)
        return $template->getResponse();
    }

}
