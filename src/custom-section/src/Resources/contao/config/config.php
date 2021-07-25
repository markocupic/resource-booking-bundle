<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 * @package Mitgliederliste RSZ
 * @link    http://www.contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

// frontend modules
array_insert
(
	$GLOBALS['FE_MOD'], 0, array
	(
		'layout' => array
		(
			'custom_section' => 'Markocupic\CustomSection\CustomSection'
		)
	)	
);
