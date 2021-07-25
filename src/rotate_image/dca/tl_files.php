<?php

/**
 * Rotate image: Backend plugin for the Contao file manager
 * Copyright (c) 2008-20 Marko Cupic
 * @package rotate_image
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/rotate_image
 */

/**
 * Operations
 */
$GLOBALS['TL_DCA']['tl_files']['list']['operations']['rotateImage'] = [
    'label'           => &$GLOBALS['TL_LANG']['tl_files']['rotateImage'],
    'href'            => 'key=rotate_image',
    'icon'            => 'system/modules/rotate_image/assets/images/arrow_rotate_clockwise.png',
    'attributes'      => 'onclick="Backend.getScrollOffset()"',
    'button_callback' => ['tl_files_rotate_image', 'rotateImage']
];

/**
 * Class tl_files_rotate_image
 */
class tl_files_rotate_image extends \Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    /**
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     * @return string
     * @throws Exception
     */
    public function rotateImage($row, $href, $label, $title, $icon, $attributes)
    {
        $isImage = false;
        $strDecoded = rawurldecode($row['id']);
        if (is_file(TL_ROOT . '/' . $strDecoded))
        {
            $objFile = new \Contao\File($strDecoded, true);
            if ($objFile->isGdImage)
            {
                $isImage = true;
            }
        }

        return $isImage == true ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . specialchars($title,
                false, true) . '"' . $attributes . '>' . \Contao\Image::getHtml($icon,
                $label) . '</a> ' : \Contao\Image::getHtml(preg_replace('/\.png$/i', '_.png', $icon)) . ' ';
    }
}
