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

namespace Markocupic\ResourceBookingBundle\Tests\Twig;

use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Twig\IconExtension;
use Symfony\Component\Filesystem\Filesystem;
use Twig\TwigFunction;

class IconExtensionTest extends ContaoTestCase
{
    private const SVG = '<svg><path/></svg>';

    public function testGetFunctionsExposesTheRbbIconFunction(): void
    {
        $functions = $this->createExtension($this->createMock(Filesystem::class))->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertSame('rbb_icon', $functions[0]->getName());
    }

    public function testGeneratesIconFromAnExistingPathAndAddsClasses(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(true)
        ;

        $filesystem
            ->method('readFile')
            ->willReturn(self::SVG)
        ;

        $result = $this->createExtension($filesystem)->generateIcon('/abs/path/icon.svg', 'foo bar');

        $this->assertStringContainsString('<svg', $result);
        // Given classes come first, then the always-present "rbb-icon".
        $this->assertStringContainsString('class="foo bar rbb-icon"', $result);
    }

    public function testGeneratesIconFromANameUsingTheDefaultFolder(): void
    {
        $filesystem = $this->createMock(Filesystem::class);

        // The bare name does not exist, but the resolved default ".svg" path does.
        $filesystem
            ->method('exists')
            ->willReturnCallback(static fn (string $path): bool => str_ends_with($path, 'fa-icon.svg'))
        ;

        $filesystem
            ->expects($this->once())
            ->method('readFile')
            ->with($this->stringEndsWith('fa-icon.svg'))
            ->willReturn(self::SVG)
        ;

        $result = $this->createExtension($filesystem)->generateIcon('fa-icon');

        $this->assertStringContainsString('class="rbb-icon"', $result);
    }

    public function testThrowsWhenTheIconCannotBeFound(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->method('exists')
            ->willReturn(false)
        ;

        $filesystem
            ->expects($this->never())
            ->method('readFile')
        ;

        $extension = $this->createExtension($filesystem);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not find icon "missing"');

        $extension->generateIcon('missing');
    }

    private function createExtension(Filesystem $filesystem): IconExtension
    {
        // specialcharsAttribute is stubbed as identity so the class list stays predictable.
        $stringUtilAdapter = $this->mockAdapter(['specialcharsAttribute']);
        $stringUtilAdapter
            ->method('specialcharsAttribute')
            ->willReturnCallback(static fn (string $value): string => $value)
        ;

        $framework = $this->mockContaoFramework([StringUtil::class => $stringUtilAdapter]);

        return new IconExtension($framework, $filesystem, '/project');
    }
}
