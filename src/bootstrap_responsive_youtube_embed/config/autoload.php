<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2016 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'MCupic',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'MCupic\ReplaceInsertTags'                      => 'system/modules/bootstrap_responsive_youtube_embed/classes/ReplaceInsertTags.php',

	// Elements
	'Contao\ContentBootstrapYoutubeResponsiveEmbed' => 'system/modules/bootstrap_responsive_youtube_embed/elements/ContentBootstrapYoutubeResponsiveEmbed.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'insert_tag_bootstrap_youtube_responsive_embed' => 'system/modules/bootstrap_responsive_youtube_embed/templates',
	'ce_bootstrap_youtube_responsive_embed'         => 'system/modules/bootstrap_responsive_youtube_embed/templates',
));
