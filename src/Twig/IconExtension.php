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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class IconExtension extends AbstractExtension
{
    /**
     * Icons of the bundle (public/icons/frontend), relative to this file: independent of where the
     * bundle is installed (vendor directory, path repository, test kernel with another project dir).
     */
    private const DEFAULT_ICON_FOLDER = __DIR__.'/../../public/icons/frontend';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Filesystem $filesystem,
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
    public function generateIcon(string $iconNameOrPath, string $class = ''): string
    {
        $class = $this->framework
            ->getAdapter(StringUtil::class)
            ->specialcharsAttribute($class)
        ;
        $classes = explode(' ', trim($class));
        $classes[] = 'rbb-icon';
        $classes = array_filter(array_unique($classes));
        $class = implode(' ', $classes);

        if ($this->filesystem->exists($iconNameOrPath)) {
            $iconPath = $iconNameOrPath;
        } else {
            // Use the default location: public/icons/frontend of the bundle
            $iconName = !str_ends_with($iconNameOrPath, '.svg') ? $iconNameOrPath.'.svg' : $iconNameOrPath;
            $iconPath = Path::join(Path::canonicalize(self::DEFAULT_ICON_FOLDER), $iconName);
        }

        if (!$this->filesystem->exists($iconPath)) {
            throw new \Exception(\sprintf('Could not find icon "%s" in "%s".', $iconNameOrPath, $iconPath));
        }

        $xml = new \SimpleXMLElement($this->filesystem->readFile($iconPath));

        if (!empty($class)) {
            $xml->addAttribute('class', $class);
        }

        return $xml->asXML();
    }
}
