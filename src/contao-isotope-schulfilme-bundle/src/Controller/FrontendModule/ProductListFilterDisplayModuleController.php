<?php

declare(strict_types=1);

/*
 * This file is part of Contao Isotope Schulfilme Bundle.
 * 
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-isotope-schulfilme-bundle
 */

namespace Markocupic\ContaoIsotopeSchulfilmeBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Date;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ProductListFilterDisplayModuleController
 */
class ProductListFilterDisplayModuleController extends AbstractFrontendModuleController
{
    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var PageModel
     */
    protected $page;

    /**
     * ProductListFilterDisplayModuleController constructor.
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * This method extends the parent __invoke method,
     * its usage is usually not necessary
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get the page model
        $this->page = $page;

        if ($this->page instanceof PageModel && $this->get('contao.routing.scope_matcher')->isFrontendRequest($request))
        {
            // If TL_MODE === 'FE'
            $this->page->loadDetails();
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * Lazyload some services
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;
        $services['contao.routing.scope_matcher'] = ScopeMatcher::class;
        $services['security.helper'] = Security::class;
        $services['translator'] = TranslatorInterface::class;

        return $services;
    }

    /**
     * Generate the module
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $userFirstname = 'DUDE';
        $user = $this->get('security.helper')->getUser();
        if ($user instanceof FrontendUser)
        {
            $userFirstname = $user->firstname;
        }

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
        $intWeekday = $dateAdapter->parse('w');
        $translator = $this->get('translator');
        $strWeekday = $translator->trans('DAYS.' . $intWeekday, [], 'contao_default');

        $arrGuests = [];
        $stmt = $this->get('database_connection')
            ->executeQuery(
                'SELECT * FROM tl_member WHERE gender=? ORDER BY lastname',
                ['female']
            );
        while (false !== ($objMember = $stmt->fetch(\PDO::FETCH_OBJ)))
        {
            $arrGuests[] = $objMember->firstname;
        }

        $template->helloTitle = sprintf(
            'Hi %s, and welcome to the "Hello World Module". Today is %s.',
            $userFirstname, $strWeekday
        );

        $template->helloText = 'Our guests today are: ' . implode(', ', $arrGuests);

        return $template->getResponse();
    }
}
