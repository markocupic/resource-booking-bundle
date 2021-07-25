<?php

/*
 * This file is part of Contao Isotope Schulfilme Bundle.
 * 
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-isotope-schulfilme-bundle
 */

use Markocupic\ContaoIsotopeSchulfilmeBundle\Model\IsoMoviesModel;

/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['iso_movies']['iso_movies'] = array(
    'tables' => array('tl_iso_movies')
);

/**
 * Models
 */
$GLOBALS['TL_MODELS']['tl_iso_movies'] = IsoMoviesModel::class;
