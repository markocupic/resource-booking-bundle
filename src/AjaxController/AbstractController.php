<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AbstractController.
 */
abstract class AbstractController
{
    protected ContaoFramework $framework;
    protected LoggedInFrontendUser $user;
    protected Utils $utils;
    protected RequestStack $requestStack;
    protected ?ArrayAttributeBag $sessionBag = null;
    protected ?ResourceBookingResourceModel $activeResource = null;
    protected ?ModuleModel $moduleModel = null;
    protected ?string $errorMsg = null;

    /**
     * AbstractController constructor.
     */
    public function __construct(ContaoFramework $framework, LoggedInFrontendUser $user, Utils $utils, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->utils = $utils;
        $this->requestStack = $requestStack;

        // Get session from request
        if (null !== ($request = $requestStack->getCurrentRequest())) {
            $this->sessionBag = $request->getSession()->getBag($bagName);
        }
    }

    /**
     * @throws \Exception
     */
    protected function initialize(): void
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        if (null === $this->user->getLoggedInUser()) {
            throw new \Exception('No logged in user found.');
        }

        // Set module model
        $this->moduleModel = $moduleModelAdapter->findByPk($this->sessionBag->get('moduleModelId'));

        if (null === $this->moduleModel) {
            throw new \Exception('Module model not found.');
        }

        // Get resource
        $request = $this->requestStack->getCurrentRequest();

        if (null === $this->getActiveResource()) {
            throw new \Exception(sprintf('Resource with Id %s not found.', $request->request->get('resourceId')));
        }

        // Get booking repeat stop week timestamp
        $this->bookingRepeatStopWeekTstamp = (int) $request->request->get('bookingRepeatStopWeekTstamp', null);

        if (null === $this->bookingRepeatStopWeekTstamp) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }
    }

    /**
     * @throws \Exception
     */
    protected function getActiveResource(): ?ResourceBookingResourceModel
    {
        if (!$this->activeResource) {
            /** @var ResourceBookingResourceModel $resourceBookingResourceModelAdapter */
            $resourceBookingResourceModelAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

            $request = $this->requestStack->getCurrentRequest();

            $this->activeResource = $resourceBookingResourceModelAdapter->findPublishedByPk($request->request->get('resourceId'));
        }

        return $this->activeResource;
    }

    protected function hasErrorMessage(): bool
    {
        return $this->errorMsg ? true : false;
    }

    protected function getErrorMessage(): ?string
    {
        return $this->errorMsg;
    }

    protected function setErrorMessage(string $error): void
    {
        $this->errorMsg = $error;
    }
}
