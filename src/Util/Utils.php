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
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Utils
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var SessionBagInterface
     */
    private $session;

    public function __construct(ContaoFramework $framework, SessionInterface $session, string $bagName)
    {
        $this->framework = $framework;
        $this->session = $session->getBag($bagName);
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
}
