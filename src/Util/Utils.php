<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Util;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

class Utils
{
    private SessionBagInterface|null $session = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        RequestStack $requestStack,
        string $bagName,
    ) {
        // Get session from request
        if (null !== ($request = $requestStack->getCurrentRequest())) {
            $this->session = $request->getSession()->getBag($bagName);
        }
    }

    /**
     * @throws \Exception
     */
    public function getModuleModel(): ModuleModel|null
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        return $moduleModelAdapter->findByPk($this->session->get('moduleModelId'));
    }

    /**
     * @throws \Exception
     */
    public function checkMandatoryFieldsSet(array $arrData, string $strTable): bool|string
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->loadDataContainer($strTable);

        if (empty($GLOBALS['TL_DCA'][$strTable])) {
            throw new \Exception('Data container array for table '.$strTable.' not found.');
        }

        $dca = $GLOBALS['TL_DCA'][$strTable]['fields'];

        foreach ($dca as $fieldName => $fieldConfig) {
            if (isset($fieldConfig['eval']['mandatory']) && true === $fieldConfig['eval']['mandatory']) {
                if (empty($arrData[$fieldName])) {
                    return $strTable.'.'.$fieldName;
                }
            }
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    public function getAppConfig(): array
    {
        if ($this->session->has('moduleModelId')) {
            $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);
            $module = $moduleModelAdapter->findByPk($this->session->get('moduleModelId'));

            if (null !== $module) {
                $strConfig = $module->resourceBooking_appConfig;

                if ('' !== (string) $strConfig) {
                    $systemAdapter = $this->framework->getAdapter(System::class);
                    $appConfig = $systemAdapter->getContainer()
                        ->getParameter('markocupic_resource_booking.apps')
                    ;

                    if (isset($appConfig[$strConfig]) && \is_array($appConfig[$strConfig])) {
                        return $appConfig[$strConfig];
                    }
                }
            }

            throw new \Exception('Could not find app configuration array. Please check your config.yml file and make sure you have created correctly your custom configuration.');
        }

        throw new \Exception('Initialize RBB application must be initialized first, before you can call '.__METHOD__.'.');
    }
}
