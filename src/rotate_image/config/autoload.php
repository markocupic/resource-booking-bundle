<?php

/**
 * Rotate image: Backend plugin for the Contao file manager
 * Copyright (c) 2008-20 Marko Cupic
 * @package rotate_image
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/rotate_image
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'Markocupic\RotateImage' => 'system/modules/rotate_image/classes/RotateImage.php',
));
