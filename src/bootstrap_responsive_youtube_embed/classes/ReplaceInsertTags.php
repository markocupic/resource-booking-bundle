<?php
/**
 * Created by PhpStorm.
 * User: Marko
 * Date: 20.01.2016
 * Time: 20:49
 */

namespace MCupic;


class ReplaceInsertTags extends \System
{


// MyClass.php
    public function replaceInsertTags($strTag)
    {
        if (strstr($strTag, 'bootstrapResponsiveYoutubeEmbed'))
        {
            $arrPieces = explode('::', $strTag);
            $n = [];
            if(!strstr($arrPieces[1], '?')){
                $id = $arrPieces[1];

            }else{
                $m = explode('?', $arrPieces[1]);
                $id = $m[0];
                $n = explode('&', $m[1]);
            }

            if($id == '') return false;


            $objTemplate = new \FrontendTemplate('ce_bootstrap_youtube_responsive_embed');
            $objTemplate->movieId = $id;
            $objTemplate->playerType = intval($id) ? 'vimeo' : 'youtube';
            $objTemplate->playerAspectRatio = 'embed-responsive-4by3';
            foreach ($n as $prop)
            {
                $pieces = explode('=', $prop);
                $objTemplate->{$pieces[0]} = $pieces[1];
            }
            return $objTemplate->parse();
        }

        return false;
    }

}