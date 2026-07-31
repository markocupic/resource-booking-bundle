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

namespace Markocupic\ResourceBookingBundle\Tests\Validator\Constraints;

use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbEndTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RbbEndTimeTest extends TestCase
{
    private const DEFAULT_MESSAGE = 'Please enter the end time in the format HH:MM. Allowed values are 00:01 to 24:00.';

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = Validation::createValidator();
    }

    public function testIsARegexConstraint(): void
    {
        $this->assertInstanceOf(Regex::class, new RbbEndTime());
    }

    public function testIsValidatedByTheRegexValidator(): void
    {
        $this->assertSame(RegexValidator::class, (new RbbEndTime())->validatedBy());
    }

    public function testHasTheExpectedDefaultConfiguration(): void
    {
        $constraint = new RbbEndTime();

        $this->assertSame('/^(?:(?:0[1-9]|1\d|2[0-3]):[0-5]\d|00:(?:0[1-9]|[1-5]\d)|24:00)$/', $constraint->pattern);
        $this->assertSame(self::DEFAULT_MESSAGE, $constraint->message);
        $this->assertSame('trim', $constraint->normalizer);
    }

    public function testOptionsOverrideTheDefaults(): void
    {
        $constraint = new RbbEndTime([
            'message' => 'Custom message',
            'pattern' => '/^\d{2}$/',
        ]);

        $this->assertSame('Custom message', $constraint->message);
        $this->assertSame('/^\d{2}$/', $constraint->pattern);
        // Untouched defaults remain in place.
        $this->assertSame('trim', $constraint->normalizer);
    }

    #[DataProvider('provideValidEndTimes')]
    public function testAcceptsValidEndTimes(string $value): void
    {
        $this->assertCount(0, $this->validator->validate($value, new RbbEndTime()));
    }

    #[DataProvider('provideInvalidEndTimes')]
    public function testRejectsInvalidEndTimes(string $value): void
    {
        $violations = $this->validator->validate($value, new RbbEndTime());

        $this->assertGreaterThan(0, \count($violations));
        $this->assertSame(self::DEFAULT_MESSAGE, $violations[0]->getMessage());
    }

    public function testUsesTheCustomMessageOnFailure(): void
    {
        $violations = $this->validator->validate('00:00', new RbbEndTime(['message' => 'Custom message']));

        $this->assertCount(1, $violations);
        $this->assertSame('Custom message', $violations[0]->getMessage());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValidEndTimes(): iterable
    {
        return [
            'one minute past midnight' => ['00:01'],
            'minute ending in zero within hour 00' => ['00:10'],
            'half past midnight' => ['00:30'],
            'ten to one' => ['00:50'],
            'last minute of first hour' => ['00:59'],
            'full hour' => ['01:00'],
            'midday-ish' => ['12:30'],
            'last minute before 24:00' => ['23:59'],
            'end of day' => ['24:00'],
            'surrounding whitespace is trimmed' => ['  24:00  '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideInvalidEndTimes(): iterable
    {
        return [
            '00:00 is not a valid end time' => ['00:00'],
            'past end of day' => ['24:01'],
            'hour out of range' => ['25:00'],
            'single digit hour' => ['9:30'],
            'minutes out of range' => ['23:60'],
            'only 24:00 is allowed for hour 24' => ['24:30'],
        ];
    }
}
