<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 10.01.2016
 * Time: 09:46
 */



$GLOBALS['TL_CTE']['media']['bootstrapYoutubeResponsiveEmbed'] = 'ContentBootstrapYoutubeResponsiveEmbed';
$GLOBALS['TL_HOOKS']['replaceInsertTags'][] = array('MCupic\ReplaceInsertTags', 'replaceInsertTags');