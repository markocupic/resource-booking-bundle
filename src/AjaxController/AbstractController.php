<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\Util\Utils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

abstract class AbstractController
{
    protected ?SessionBagInterface $sessionBag = null;
    protected ?ResourceBookingResourceModel $activeResource = null;
    protected ?ModuleModel $moduleModel = null;
    protected ?string $errorMsg = null;

    public function __construct(
        protected ContaoFramework $framework,
        protected LoggedInFrontendUser $user,
        protected Utils $utils,
        protected RequestStack $requestStack,
        string $bagName,
    ) {
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

        if (!$request->request->has('bookingRepeatStopWeekTstamp')) {
            throw new \Exception('No booking repeat stop week timestamp found.');
        }

        // Get booking repeat stop week timestamp
        $this->bookingRepeatStopWeekTstamp = (int) $request->request->get('bookingRepeatStopWeekTstamp');
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

            $this->activeResource = $resourceBookingResourceModelAdapter->findPublishedByPk((int) $request->request->get('resourceId'));
        }

        return $this->activeResource;
    }

    protected function hasErrorMessage(): bool
    {
        return (bool) $this->errorMsg;
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
