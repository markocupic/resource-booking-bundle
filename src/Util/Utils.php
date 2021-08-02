<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Util;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\System;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Symfony\Component\HttpFoundation\RequestStack;

class Utils
{
    private ContaoFramework $framework;
    private ?ArrayAttributeBag $session = null;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, string $bagName)
    {
        $this->framework = $framework;

        // Get session from request
        if (null !== ($request = $requestStack->getCurrentRequest())) {
            $this->session = $request->getSession()->getBag($bagName);
        }
    }

    /**
     * @throws \Exception
     */
    public function getModuleModel(): ?ModuleModel
    {
        /** @var ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        return $moduleModelAdapter->findByPk($this->session->get('moduleModelId'));
    }

    /**
     * @param $arrData
     * @param $strDcaTable
     *
     * @throws \Exception
     *
     * @return bool|string
     */
    public function areMandatoryFieldsSet($arrData, $strDcaTable)
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $controllerAdapter->loadDataContainer($strDcaTable);

        if (empty($GLOBALS['TL_DCA'][$strDcaTable])) {
            throw new \Exception('Data container array for table '.$strDcaTable.' not found.');
        }

        $dca = $GLOBALS['TL_DCA'][$strDcaTable]['fields'];

        foreach ($dca as $fieldname => $fieldConfig) {
            if (isset($fieldConfig['eval']['mandatory']) && true === $fieldConfig['eval']['mandatory']) {
                if (!isset($arrData[$fieldname]) || empty($arrData[$fieldname])) {
                    return $strDcaTable.'.'.$fieldname;
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

            throw new \Exception('Could not find app configuration array. Please check your config.yml file an make sure you have created correctly your custom configuration.');
        }

        throw new \Exception('Initialize RBB application must be initialized first, before you can call '.__METHOD__.'.');
    }
}
