<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao;


/**
 * Front end content element "download".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ContentBootstrapYoutubeResponsiveEmbed extends \ContentElement
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'ce_bootstrap_youtube_responsive_embed';


	/**
	 * Extend the parent method
	 *
	 * @return string
	 */
	public function generate()
	{
		if ($this->movieId == '')
		{
			return '';
		}

		// Set the size
		if ($this->playerAspectRatio == '')
		{
			$this->playerAspectRatio = 'embed-responsive-16by9';
		}

		if (TL_MODE == 'BE')
		{

			if($this->playerType == 'youtube')
			{
				return '<p><a href="//youtu.be/' . $this->movieId . '" target="_blank">http://youtu.be/' . $this->movieId . '</a><br>Anzeigeverh&auml;ltnis: ' . $GLOBALS['TL_LANG']['tl_content'][$this->playerAspectRatio] . '<br>CSS-Class: ' . $this->cssID[1] .'</p>';
			}
			if($this->playerType == 'vimeo')
			{
				return '<p><a href="//player.vimeo.com/video/' . $this->movieId . '" target="_blank">https://player.vimeo.com/video/' . $this->movieId . '</a><br>Anzeigeverh&auml;ltnis: ' . $GLOBALS['TL_LANG']['tl_content'][$this->playerAspectRatio] . '<br>CSS-Class: ' . $this->cssID[1] .'</p>';
			}
			if($this->playerType == 'dropbox')
			{
				return '<p><a href="//dl.dropbox.com/' . $this->movieId . '" target="_blank">http://dl.dropbox.com/' . $this->movieId . '</a><br>Anzeigeverh&auml;ltnis: ' . $GLOBALS['TL_LANG']['tl_content'][$this->playerAspectRatio] . '<br>CSS-Class: ' . $this->cssID[1] .'</p>';
			}
		}

		return parent::generate();
	}


	/**
	 * Generate the module
	 */
	protected function compile()
	{

		$this->Template->autoplay = $this->autoplay;

	}
}