<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Utils;

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
}
