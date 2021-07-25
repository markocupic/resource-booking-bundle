<?php

/**
 * Export table module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package export_table
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/export_table
 */

/**
 * Back end modules
 */
if (TL_MODE === 'BE')
{
    $GLOBALS['BE_MOD']['system']['export_table'] = array(
        'tables' => array(
            'tl_export_table',
        ),
    );
}


if (TL_MODE === 'BE' && \Contao\Input::get('do') === 'export_table')
{
    $GLOBALS['TL_CSS'][] = 'bundles/markocupicexporttable/export_table.css';
}

// ****** exportTable Hook *********
// With the exportTable Hook you can control the output
// Please ensure that the hook container will be loaded before the export_table container.
// In Contao 4 you have to load the hook container via the AppKernel.php right before the export_table container
// $GLOBALS['TL_HOOKS']['exportTable'][] = array('\MyNamespace\MyPackage\MyClass','myMethod');
