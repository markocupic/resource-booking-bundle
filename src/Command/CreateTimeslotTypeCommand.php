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

namespace Markocupic\ResourceBookingBundle\Command;

use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Util\UtcTimeHelper;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbEndTime;
use Markocupic\ResourceBookingBundle\Validator\Constraints\RbbStartTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'rbb:create-slots',
    description: 'Create time slots via the Contao Console.',
)]
class CreateTimeslotTypeCommand extends Command
{
    private string $scheduleName = '';

    private int $startTimestamp = 0;

    private int $stopTimestamp = 0;

    private int $slotDuration = 0;

    public function __construct(
        private readonly Connection $connection,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $this->collectInput($io);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        $arrSlots = [];
        $i = 0;

        // Set the start and end time of the first slot
        $startTimestamp = $this->startTimestamp;
        $endTimestamp = $this->startTimestamp + $this->slotDuration * 60;

        while ($endTimestamp <= $this->stopTimestamp) {
            $arrSlots[] = [
                'title' => 'Slot '.$i,
                'startTimestamp' => $startTimestamp,
                'endTimestamp' => $endTimestamp,
                'startTimeFormatted' => UtcTimeHelper::parseStartTime($startTimestamp),
                'endTimeFormatted' => UtcTimeHelper::parseEndTime($endTimestamp),
            ];

            $startTimestamp = $endTimestamp;
            $endTimestamp = $startTimestamp + $this->slotDuration * 60;

            ++$i;
        }

        if (empty($arrSlots)) {
            $io->error('Could not create any slots. Please check your inputs. The slot end time cannot exceed the stop time.');

            return Command::INVALID;
        }

        $this->connection->beginTransaction();

        try {
            $set = [
                'title' => $this->scheduleName,
                'tstamp' => time(),
                'published' => 0,
                'description' => '',
            ];

            // Persist the new schedule
            $inserts = $this->connection->insert('tl_resource_booking_time_slot_type', $set);

            $messages = [];

            if ($inserts) {
                $pid = $this->connection->lastInsertId();

                if (!$pid) {
                    throw new \Exception('Could not get last insert id.');
                }

                $messages[] = \sprintf('<fg=green>Created new schedule: "%s"</>', $this->scheduleName);

                foreach ($arrSlots as $i => $slot) {
                    $set = [
                        'pid' => (int) $pid,
                        'title' => (string) $slot['title'],
                        'tstamp' => time(),
                        'published' => 1,
                        'startTime' => $slot['startTimestamp'],
                        'endTime' => $slot['endTimestamp'],
                        'sorting' => $i * 100,
                    ];

                    // Persist the new time slot
                    $this->connection->insert('tl_resource_booking_time_slot', $set);

                    $messages[] = \sprintf('<fg=green>New time slot created: %s - %s</>', $slot['startTimeFormatted'], $slot['endTimeFormatted']);
                }
            }

            foreach ($messages as $message) {
                $io->writeln($message);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Operation successfully completed. Created %s new time slots in schedule "%s". You have to publish the schedule manually in the Contao Backend.', \count($arrSlots), $this->scheduleName));

        return Command::SUCCESS;
    }

    private function collectInput(SymfonyStyle $io): void
    {
        // Input with immediate validation
        $this->scheduleName = $this->askAndValidate(
            $io,
            'Please enter the name of the new schedule.',
            [
                new NotBlank(message: 'This value cannot be empty.'),
                new Type('string', message: 'The value must be a string.'),
                new Regex(
                    pattern: '/^[A-Za-z0-9 _-]+$/',
                    message: 'Only letters, numbers, spaces, hyphens, and underscores are allowed.',
                ),
                new Length(
                    min: 4,
                    max: 200,
                    minMessage: 'The name must be at least {{ limit }} characters long.',
                    maxMessage: 'The name cannot be longer than {{ limit }} characters.',
                ),
            ],
        );

        $startTime = $this->askAndValidate(
            $io,
            'Please enter the schedule start time in the format HH:MM. Allowed values are 00:00 to 23:59.',
            [
                new RbbStartTime(),
            ],
        );

        $endTime = $this->askAndValidate(
            $io,
            'Please enter the schedule end time in the format HH:MM. Notice: The end time must be greater than the start time.',
            [
                new RbbEndTime(),
            ],
        );

        $this->startTimestamp = UtcTimeHelper::strToTime('1970-01-01 '.$startTime);
        $this->stopTimestamp = UtcTimeHelper::strToTime('1970-01-01 '.$endTime);

        if ($this->stopTimestamp <= $this->startTimestamp) {
            throw new \RuntimeException('End time must be greater than start time.');
        }

        $this->slotDuration = (int) $this->askAndValidate(
            $io,
            'Please enter the interval as an integer in minutes.',
            [
                new Callback(
                    static function ($value, $context): void {
                        $int = (int) $value;

                        // 1440 minutes = 24 hours
                        if ($int < 1 || $int > 1440) {
                            $context
                                ->buildViolation('Please enter a value between 1 and 1440 minutes (1440 min = 24 hours).')
                                ->addViolation()
                            ;
                        }
                    },
                ),
            ],
        );
    }

    private function askAndValidate(SymfonyStyle $io, string $question, array $constraints): mixed
    {
        while (true) {
            $value = $io->ask($question);
            $errors = $this->validator->validate($value, $constraints);

            if (0 === \count($errors)) {
                return $value;
            }

            foreach ($errors as $error) {
                $io->error($error->getMessage());
            }
        }
    }
}
