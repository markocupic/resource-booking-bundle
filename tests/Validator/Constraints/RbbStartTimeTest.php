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

use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbStartTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RbbStartTimeTest extends TestCase
{
    private const DEFAULT_MESSAGE = 'Please enter the start time in the format HH:MM. Allowed values are 00:00 to 23:59.';

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = Validation::createValidator();
    }

    public function testIsARegexConstraint(): void
    {
        $this->assertInstanceOf(Regex::class, new RbbStartTime());
    }

    public function testIsValidatedByTheRegexValidator(): void
    {
        $this->assertSame(RegexValidator::class, (new RbbStartTime())->validatedBy());
    }

    public function testHasTheExpectedDefaultConfiguration(): void
    {
        $constraint = new RbbStartTime();

        $this->assertSame('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $constraint->pattern);
        $this->assertSame(self::DEFAULT_MESSAGE, $constraint->message);
        $this->assertSame('trim', $constraint->normalizer);
    }

    public function testOptionsOverrideTheDefaults(): void
    {
        $constraint = new RbbStartTime([
            'message' => 'Custom message',
            'pattern' => '/^\d{2}$/',
        ]);

        $this->assertSame('Custom message', $constraint->message);
        $this->assertSame('/^\d{2}$/', $constraint->pattern);
        // Untouched defaults remain in place.
        $this->assertSame('trim', $constraint->normalizer);
    }

    #[DataProvider('provideValidStartTimes')]
    public function testAcceptsValidStartTimes(string $value): void
    {
        $this->assertCount(0, $this->validator->validate($value, new RbbStartTime()));
    }

    #[DataProvider('provideInvalidStartTimes')]
    public function testRejectsInvalidStartTimes(string $value): void
    {
        $violations = $this->validator->validate($value, new RbbStartTime());

        $this->assertGreaterThan(0, \count($violations));
        $this->assertSame(self::DEFAULT_MESSAGE, $violations[0]->getMessage());
    }

    public function testUsesTheCustomMessageOnFailure(): void
    {
        $violations = $this->validator->validate('24:00', new RbbStartTime(['message' => 'Custom message']));

        $this->assertCount(1, $violations);
        $this->assertSame('Custom message', $violations[0]->getMessage());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideValidStartTimes(): iterable
    {
        return [
            'midnight' => ['00:00'],
            'morning' => ['09:30'],
            'noon' => ['12:00'],
            'last minute of the day' => ['23:59'],
            'surrounding whitespace is trimmed' => ['  08:15  '],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideInvalidStartTimes(): iterable
    {
        return [
            '24:00 is out of range' => ['24:00'],
            'minutes out of range' => ['23:60'],
            'single digit hour' => ['9:30'],
            'single digit hour and minute' => ['1:2'],
            'not a time at all' => ['ab:cd'],
            'too many digits' => ['007:00'],
            'hour above 23' => ['24:01'],
        ];
    }
}
