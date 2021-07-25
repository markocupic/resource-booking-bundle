<?php

declare(strict_types=1);

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

namespace Markocupic\ExportTable;

use Contao\System;

/**
 * Class ExportTable
 * Provide backward compatibility to versions < 3.2
 * @package Markocupic\ExportTable
 */
class ExportTable
{
    /**
     * @param string $strTable
     * @param array $options
     */
    public static function exportTable(string $strTable, array $options = [])
    {
        $export = System::getContainer()->get('Markocupic\ExportTable\Export\ExportTable');
        $export->exportTable($strTable, $options);
    }
}

