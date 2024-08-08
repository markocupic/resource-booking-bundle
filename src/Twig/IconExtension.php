<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Twig;

use Contao\StringUtil;
use Safe\Exceptions\FilesystemException;
use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use function Safe\file_get_contents;

class IconExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('rbb_icon', [$this, 'generateIcon']),
        ];
    }

    /**
     * Usage in your TWIG template:
     * {{ rbb_icon('fa-my-icon')|raw }} // The default icon folder will be used and the extension ".svg" will be added
     * {{ rbb_icon('fa-my-icon.svg')|raw }} // The default icon folder will be used
     * {{ rbb_icon('/absolute_path/to/my/icon/fa-my-icon.svg')|raw }} // Use the absolute path to your icon.
     *
     * @param string $iconNameOrPath
     *
     * @throws FilesystemException
     */
    public function generateIcon(string $iconNameOrPath, string $strClass = ''): string
    {
        $strClass = StringUtil::specialcharsAttribute($strClass);
        $classes = explode(' ', trim($strClass));
        $classes[] = 'rbb-icon';
        $classes = array_filter(array_unique($classes));
        $strClass = implode(' ', $classes);

        if (is_file($iconNameOrPath)) {
            $iconPath = $iconNameOrPath;
        } else {
            // Use the default location:  'vendor/markocupic/resource-booking-bundle/public/icons/frontend'
            $iconName = !str_ends_with($iconNameOrPath, '.svg') ? $iconNameOrPath.'.svg' : $iconNameOrPath;
            $dirname = Path::join($this->projectDir, 'vendor/markocupic/resource-booking-bundle/public/icons/frontend');
            $iconPath = Path::join($dirname, $iconName);
        }

        if(!is_file($iconPath)){
            throw new \Exception(sprintf('Could not find icon "%s" in "%s".',$iconNameOrPath,$iconPath));
        }

        $strXml = file_get_contents($iconPath);
        $xml = new \SimpleXMLElement($strXml);

        if (!empty($strClass)) {
            $xml->addAttribute('class', $strClass);
        }

        return $xml->asXML();
    }
}
