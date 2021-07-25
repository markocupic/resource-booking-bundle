<?php

declare(strict_types=1);

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

namespace Markocupic\ExportTable\Controller;

use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Markocupic\ExportTable\Export\ExportTable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DownloadController
 * @package Markocupic\ExportTable\Controller
 */
class DownloadController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ExportTable
     */
    private $exportTable;

    /**
     * DownloadController constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param Connection $connection
     * @param ExportTable $exportTable
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, ExportTable $exportTable)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->exportTable = $exportTable;

        $this->framework->initialize();
    }

    /**
     * This controller is used to export table via deeplink
     * @Route("/_export_table_download_table", name="export_table_download_table", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function downloadAction()
    {
        $this->exportTable->prepareExport();
        exit;
    }
}
