<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Twig;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IconExtension extends AbstractExtension
{
    private const DEFAULT_ICON_FOLDER = 'vendor/markocupic/resource-booking-bundle/public/icons/frontend';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
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
     */
    public function generateIcon(string $iconNameOrPath, string $strClass = ''): string
    {
        $strClass = $this->framework
            ->getAdapter(StringUtil::class)
            ->specialcharsAttribute($strClass)
        ;
        $classes = explode(' ', trim($strClass));
        $classes[] = 'rbb-icon';
        $classes = array_filter(array_unique($classes));
        $strClass = implode(' ', $classes);

        if ($this->filesystem->exists($iconNameOrPath)) {
            $iconPath = $iconNameOrPath;
        } else {
            // Use the default location:  'vendor/markocupic/resource-booking-bundle/public/icons/frontend'
            $iconName = !str_ends_with($iconNameOrPath, '.svg') ? $iconNameOrPath.'.svg' : $iconNameOrPath;
            $dirname = Path::join($this->projectDir, self::DEFAULT_ICON_FOLDER);
            $iconPath = Path::join($dirname, $iconName);
        }

        if (!$this->filesystem->exists($iconPath)) {
            throw new \Exception(\sprintf('Could not find icon "%s" in "%s".', $iconNameOrPath, $iconPath));
        }

        $strXml = $this->filesystem->readFile($iconPath);
        $xml = new \SimpleXMLElement($strXml);

        if (!empty($strClass)) {
            $xml->addAttribute('class', $strClass);
        }

        return $xml->asXML();
    }
}
