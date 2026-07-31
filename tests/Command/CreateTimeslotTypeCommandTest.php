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

namespace Markocupic\ResourceBookingBundle\Tests\Command;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Command\CreateTimeslotTypeCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CreateTimeslotTypeCommandTest extends TestCase
{
    private const SCHEDULE_TABLE = 'tl_resource_booking_time_slot_type';

    private const SLOT_TABLE = 'tl_resource_booking_time_slot';

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = Validation::createValidator();
    }

    public function testCreatesScheduleAndSlotsAndCommits(): void
    {
        $insertedTables = [];

        $connection = $this->createConnection();
        $connection
            ->method('insert')
            ->willReturnCallback(
                static function (string $table, array $data) use (&$insertedTables): int {
                    $insertedTables[] = $table;

                    return 1;
                },
            )
        ;
        $connection
            ->method('lastInsertId')
            ->willReturn('7')
        ;

        $connection
            ->expects($this->once())
            ->method('beginTransaction')
        ;

        $connection
            ->expects($this->once())
            ->method('commit')
        ;

        $connection
            ->expects($this->never())
            ->method('rollBack')
        ;

        // 08:00–10:00 in 30 minute steps => 4 slots (+1 schedule insert).
        $tester = $this->createTester($connection);
        $tester->setInputs(['Testplan', '08:00', '10:00', '30']);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(
            [
                self::SCHEDULE_TABLE,
                self::SLOT_TABLE,
                self::SLOT_TABLE,
                self::SLOT_TABLE,
                self::SLOT_TABLE,
            ],
            $insertedTables,
        );
        $this->assertStringContainsString('Created new schedule: "Testplan"', $this->normalizedDisplay($tester));
        $this->assertStringContainsString('Operation successfully completed', $this->normalizedDisplay($tester));
        // The reported count must match the number of slots actually created.
        $this->assertStringContainsString('Created 4 new time slots', $this->normalizedDisplay($tester));
    }

    public function testFailsWhenEndTimeIsNotAfterStartTime(): void
    {
        $connection = $this->createConnection();

        // The cross-field check happens before any DB interaction.
        $connection
            ->expects($this->never())
            ->method('beginTransaction')
        ;

        $tester = $this->createTester($connection);
        $tester->setInputs(['Testplan', '10:00', '09:00']);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('End time must be greater than start time', $this->normalizedDisplay($tester));
    }

    public function testFailsWhenNoSlotFitsIntoTheRange(): void
    {
        $connection = $this->createConnection();
        $connection
            ->expects($this->never())
            ->method('beginTransaction')
        ;

        // A 30 minute interval does not fit into a 20 minute window.
        $tester = $this->createTester($connection);
        $tester->setInputs(['Testplan', '08:00', '08:20', '30']);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString('Could not create any slots', $this->normalizedDisplay($tester));
    }

    public function testRollsBackAndFailsOnDatabaseError(): void
    {
        $connection = $this->createConnection();
        $connection
            ->method('insert')
            ->willThrowException(new \RuntimeException('db down'))
        ;

        $connection
            ->expects($this->once())
            ->method('beginTransaction')
        ;

        $connection
            ->expects($this->once())
            ->method('rollBack')
        ;

        $connection
            ->expects($this->never())
            ->method('commit')
        ;

        $tester = $this->createTester($connection);
        $tester->setInputs(['Testplan', '08:00', '10:00', '30']);

        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('db down', $this->normalizedDisplay($tester));
    }

    /**
     * @return Connection&MockObject
     */
    private function createConnection(): Connection
    {
        return $this->createMock(Connection::class);
    }

    private function createTester(Connection $connection): CommandTester
    {
        return new CommandTester(new CreateTimeslotTypeCommand($connection, $this->validator));
    }

    /**
     * SymfonyStyle blocks wrap long text at the terminal width, which can insert
     * line breaks mid-sentence. Collapse all whitespace so substring assertions
     * stay robust.
     */
    private function normalizedDisplay(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
