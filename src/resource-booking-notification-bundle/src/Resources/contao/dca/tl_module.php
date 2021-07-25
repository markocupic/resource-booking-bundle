<?php

/*
 * This file is part of Resource Booking Notification Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license LGPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-notification-bundle
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Palettes
PaletteManipulator::create()
	->addLegend('notification_legend', 'config_legend', PaletteManipulator::POSITION_AFTER)
	->addField(array('rbbOnBookingNotification,rbbOnCancelingNotification'), 'notification_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('resourceBookingWeekcalendar', 'tl_module');

$GLOBALS['TL_DCA']['tl_module']['fields']['rbbOnBookingNotification'] = array(
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_nc_notification.title',
	'eval'       => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);

$GLOBALS['TL_DCA']['tl_module']['fields']['rbbOnCancelingNotification'] = array(
	'exclude'    => true,
	'search'     => true,
	'inputType'  => 'select',
	'foreignKey' => 'tl_nc_notification.title',
	'eval'       => array('includeBlankOption' => true, 'tl_class' => 'w50'),
	'sql'        => "int(10) unsigned NOT NULL default '0'",
	'relation'   => array('type' => 'hasOne', 'load' => 'lazy'),
);
