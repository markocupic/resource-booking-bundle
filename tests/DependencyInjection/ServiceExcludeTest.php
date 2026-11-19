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

namespace Markocupic\ResourceBookingBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Classes with a private constructor (static helpers, value objects with named constructors)
 * must be excluded from the service loading of config/services.yaml. Otherwise the container
 * cannot be compiled: "Invalid service …: its constructor must be public".
 */
class ServiceExcludeTest extends TestCase
{
    private const CONFIG_DIR = __DIR__.'/../../config';

    public function testClassesWithAPrivateConstructorAreNoServices(): void
    {
        $excluded = self::getExcludedFiles();
        $missing = [];

        foreach (self::getClassFilesWithPrivateConstructor() as $file) {
            if (!\in_array($file, $excluded, true)) {
                $missing[] = substr($file, \strlen((string) realpath(self::CONFIG_DIR.'/../src')) + 1);
            }
        }

        $this->assertSame([], $missing, 'Add these classes to the "exclude" list in config/services.yaml.');
    }

    /**
     * @return list<string>
     */
    private static function getExcludedFiles(): array
    {
        $config = Yaml::parseFile(self::CONFIG_DIR.'/services.yaml');
        $files = [];

        foreach ($config['services']['Markocupic\\ResourceBookingBundle\\']['exclude'] as $pattern) {
            foreach (self::expandBraces(self::CONFIG_DIR.'/'.$pattern) as $path) {
                foreach (glob($path) ?: [] as $match) {
                    if (is_dir($match)) {
                        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($match, \FilesystemIterator::SKIP_DOTS)) as $file) {
                            $files[] = (string) realpath((string) $file);
                        }
                    } else {
                        $files[] = (string) realpath($match);
                    }
                }
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function getClassFilesWithPrivateConstructor(): array
    {
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::CONFIG_DIR.'/../src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ('php' === $file->getExtension() && preg_match('/private\s+function\s+__construct\s*\(/', (string) file_get_contents((string) $file))) {
                $files[] = (string) realpath((string) $file);
            }
        }

        sort($files);

        return $files;
    }

    /**
     * "a/{b,c}.php" => ["a/b.php", "a/c.php"] (GLOB_BRACE is not available on every system).
     *
     * @return list<string>
     */
    private static function expandBraces(string $pattern): array
    {
        if (!preg_match('/\{([^{}]*)\}/', $pattern, $match, PREG_OFFSET_CAPTURE)) {
            return [$pattern];
        }

        $paths = [];

        foreach (explode(',', $match[1][0]) as $option) {
            $paths = [...$paths, ...self::expandBraces(substr_replace($pattern, $option, $match[0][1], \strlen($match[0][0])))];
        }

        return $paths;
    }
}
