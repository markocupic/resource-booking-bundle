<?php

declare(strict_types=1);

/*
 * This file is part of markocupic/contao-schule-ettiswil-licenses-bundle.
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */

namespace Markocupic\ContaoSchuleEttiswilLicensesBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;

/**
 * Class SchuleEttiswilLicensesListModuleController.
 */
class SchuleEttiswilLicensesListModuleController extends AbstractFrontendModuleController
{
    /**
     * @var TwigEnvironment
     */
    protected $twig;

    /**
     * SchuleEttiswilLicensesListModuleController constructor.
     */
    public function __construct(TwigEnvironment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * This method extends the parent __invoke method,
     * its usage is usually not necessary.
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * Lazyload some services.
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $arrLicenses = [];
        $stmt = $this->get('database_connection')
            ->executeQuery(
                'SELECT * FROM tl_schule_ettiswil_licenses ORDER BY department',
                ['female']
            )
        ;

        while (false !== ($objLicenses = $stmt->fetch(\PDO::FETCH_OBJ))) {
            if(!empty($objLicenses->expirationdate))
            {
                $objLicenses->expirationdate = date('Y-m-d', (int) $objLicenses->expirationdate);
            }

            if(!empty($objLicenses->tstamp))
            {
                $objLicenses->tstamp = date('Y-m-d', (int) $objLicenses->tstamp);
            }

            $arrLicenses[] = $objLicenses;
        }

        $template->licenses = $arrLicenses;

        /*
         * Use twig template
         */
        return new Response($this->twig->render(
            '@MarkocupicContaoSchuleEttiswilLicenses/FrontendModule/SchuleEttiswilLicensesListModule/mod_schule_ettiswil_licenses_list_module.html.twig',
            [
                'data' => $template,
            ]
        ));
    }
}
