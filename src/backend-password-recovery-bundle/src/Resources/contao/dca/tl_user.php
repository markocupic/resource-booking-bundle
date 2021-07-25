<?php

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

$GLOBALS['TL_DCA']['tl_user']['fields']['activation'] = array(
	'sql' => "varchar(64) NOT NULL default ''"
);
