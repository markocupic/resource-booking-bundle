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

namespace Markocupic\ResourceBookingBundle\Tests\Util;

use Markocupic\ResourceBookingBundle\Util\CsvList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvListTest extends TestCase
{
    public static function valueProvider(): iterable
    {
        yield 'csv' => ['monday,tuesday', ['monday', 'tuesday']];
        yield 'csv with spaces and empty items' => [' monday, ,tuesday,', ['monday', 'tuesday']];
        yield 'single value' => ['3', ['3']];
        yield 'serialized (not migrated yet)' => [serialize(['monday', 'tuesday']), ['monday', 'tuesday']];
        yield 'serialized ids' => [serialize([4, '7']), ['4', '7']];
        yield 'array' => [['4', 7, ''], ['4', '7']];
        yield 'empty string' => ['', []];
        yield 'null' => [null, []];
        yield 'serialized empty array' => [serialize([]), []];
    }

    #[DataProvider('valueProvider')]
    public function testParsesCsvSerializedArraysAndArrays(mixed $value, array $expected): void
    {
        $this->assertSame($expected, CsvList::parse($value));
    }

    public function testParsesIds(): void
    {
        $this->assertSame([4, 7], CsvList::parseIds('4,7'));
        $this->assertSame([4, 7], CsvList::parseIds(serialize(['4', '7'])));
    }

    public function testInvalidSerializedValuesResultInAnEmptyList(): void
    {
        $this->assertSame([], CsvList::parse('a:2:{i:0;s:6:"monday"'));
    }

    public function testCreatesTheCsvString(): void
    {
        $this->assertSame('monday,tuesday', CsvList::toString(['monday', ' tuesday ', '']));
        $this->assertSame('4,7', CsvList::toString([4, 7]));
        $this->assertSame('', CsvList::toString([]));
    }
}
