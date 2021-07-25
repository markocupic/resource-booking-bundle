<?php

/**
 * Rotate image: Backend plugin for the Contao file manager
 * Copyright (c) 2008-20 Marko Cupic
 * @package rotate_image
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/rotate_image
 */

namespace Markocupic;

use Contao\Backend;
use Contao\Controller;
use Contao\File;
use Contao\Input;
use Contao\Message;

/**
 * Class RotateImage
 * @package Markocupic
 */
class RotateImage extends Backend
{

    /**
     * Rotate an image clockwise by 90°
     * @throws \ImagickException
     */
    public function rotateImage()
    {
        $src = html_entity_decode(Input::get('id'));

        if (!file_exists(TL_ROOT . '/' . $src))
        {
            Message::addError(sprintf('File "%s" not found.', $src));
            Controller::redirect($this->getReferer());
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));
            Controller::redirect($this->getReferer());
        }

        if (class_exists('Imagick') && class_exists('ImagickPixel'))
        {
            $angle = 90;
            $imagick = new \Imagick();
            $imagick->readImage(TL_ROOT . '/' . $src);
            $imagick->rotateImage(new \ImagickPixel('none'), $angle);
            $imagick->writeImage(TL_ROOT . '/' . $src);
            $imagick->clear();
            $imagick->destroy();
            Controller::redirect($this->getReferer());
        }
        elseif (function_exists('imagerotate'))
        {
            $angle = 270;
            $source = imagecreatefromjpeg(TL_ROOT . '/' . $src);

            //rotate
            $imgTmp = imagerotate($source, $angle, 0);

            // Output
            imagejpeg($imgTmp, TL_ROOT . '/' . $src);

            imagedestroy($source);
        }
        else
        {
            Message::addError(sprintf('Please install class "%s" or php function "%s" for rotating images.', 'Imagick', 'imagerotate'));
        }
        Controller::redirect($this->getReferer());
    }

}

