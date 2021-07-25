<?php
/**
 * @copyright  Marko Cupic 2017 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Service Link Bundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/markocupic/service-link-bundle
 *
 */


/**
 * Contao Content Elements
 */
array_insert($GLOBALS['TL_CTE'], 2, array('ce_serviceLink' => array('serviceLink' => 'Markocupic\ServiceLinkBundle\ContaoElements\ServiceLink')));

/**
 * Javascript
 */
if (TL_MODE == 'FE')
{
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicservicelink/js/ce_servicelink.js|static';
}
