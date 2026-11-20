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

namespace Markocupic\ResourceBookingBundle\Tests\Integration;

use Contao\FrontendUser;
use Contao\Model\Registry;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\FunctionalTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\EventListener\ReduceResponseDataListener;
use Markocupic\ResourceBookingBundle\EventListener\RenderWeekCalendarListener;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingBlockedPeriodModel;
use Markocupic\ResourceBookingBundle\Slot\SlotDataProvider;
use Markocupic\ResourceBookingBundle\Tests\Integration\app\AppKernel;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Base class of the integration tests: Symfony kernel with Contao (see app/AppKernel.php)
 * and a real MySQL/MariaDB database. The tables are created from the DCA files of
 * Contao and of the bundle, as in the functional tests of Contao.
 *
 * The connection is read from the environment variable RBB_TEST_DATABASE_URL, e.g.
 * mysql://rbb_test:rbb_test@127.0.0.1:3306/rbb_test (see tools/phpunit/batch/create-test-database.bat).
 * Without it, the tests are skipped. The database is emptied: its name must contain "test".
 */
abstract class IntegrationTestCase extends FunctionalTestCase
{
    public const DATABASE_URL_VARIABLE = 'RBB_TEST_DATABASE_URL';

    /**
     * Tables emptied before every test.
     */
    private const TABLES = [
        'tl_member',
        'tl_module',
        'tl_page',
        'tl_resource_booking',
        'tl_resource_booking_blocked_period',
        'tl_resource_booking_resource',
        'tl_resource_booking_resource_type',
        'tl_resource_booking_time_slot',
        'tl_resource_booking_time_slot_type',
    ];

    protected Connection $connection;

    /**
     * The kernel cache is rebuilt once per test run (see clearKernelCache()).
     */
    private static bool $kernelCacheCleared = false;

    protected function setUp(): void
    {
        parent::setUp();

        $databaseUrl = self::getDatabaseUrl();

        if (null === $databaseUrl) {
            $this->markTestSkipped(\sprintf('Set the environment variable %s to run the integration tests (see tools/phpunit/batch/create-test-database.bat).', self::DATABASE_URL_VARIABLE));
        }

        $databaseName = ltrim((string) parse_url($databaseUrl, PHP_URL_PATH), '/');

        if (!str_contains($databaseName, 'test')) {
            $this->fail(\sprintf('The integration tests empty the database. Its name must contain "test", "%s" given.', $databaseName));
        }

        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        $_SERVER['APP_SECRET'] ??= 'rbb-integration-tests';
        $_ENV['APP_SECRET'] = $_SERVER['APP_SECRET'];

        self::clearKernelCache();
        self::bootKernel();

        $this->connection = self::getContainer()->get('database_connection');

        // Creates the tables once; then the tables are only emptied
        self::resetDatabaseSchema();

        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('TRUNCATE TABLE '.$table);
        }

        // The IDs start at 1 again: forget the models and caches of previous tests (static)
        self::resetRequestState();

        // Front end request as in the API controller
        $request = Request::create('https://example.com/');
        $request->attributes->set('_scope', 'frontend');
        self::getContainer()->get('request_stack')->push($request);
        self::getContainer()->get('contao.framework')->initialize();
    }

    protected function tearDown(): void
    {
        if (self::$booted) {
            self::getContainer()->get('security.token_storage')->setToken(null);
        }

        unset($GLOBALS['objPage']);

        parent::tearDown();
    }

    /**
     * Sends a request of the week calendar API to the Ajax controller of the action,
     * as WeekCalendarApiController does after the access checks.
     *
     * @return array<string, mixed> The JSON response, plus "fullData": all data of the response before
     *                              ReduceResponseDataListener removed what the HTML was rendered from
     */
    protected function sendApiRequest(Request $request, string $action, int $moduleId, int $pageId): array
    {
        // Every API request is a new HTTP request: nothing is cached from previous requests
        self::resetRequestState();

        $page = PageModel::findById($pageId);
        $page->loadDetails();
        $module = ModuleModel::findById($moduleId);

        $request->attributes->set('_scope', 'frontend');
        $request->attributes->set('pageModel', $page);
        $request->setLocale('de');
        $GLOBALS['objPage'] = $page;

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        // Only the errors of this request
        $errorLog = AppKernel::getErrorLogPath();

        if (is_file($errorLog)) {
            file_put_contents($errorLog, '');
        }

        // The data the HTML is rendered from, before it is removed from the JSON response (ReduceResponseDataListener)
        $fullData = null;
        $captureData = static function (AjaxResponseEvent $event) use (&$fullData): void {
            $fullData = $event->getAjaxResponse()->getAllData();
        };

        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(AjaxResponseEvent::class, $captureData, (RenderWeekCalendarListener::PRIORITY + ReduceResponseDataListener::PRIORITY) / 2);

        try {
            $response = self::getContainer()
                ->get(AjaxRequestHandler::class)
                ->handle($request, $action, new ApiContext($module, $page, 'test'))
            ;
        } finally {
            $requestStack->pop();
            $eventDispatcher->removeListener(AjaxResponseEvent::class, $captureData);
        }

        // The handler answers exceptions with a general message: display the logged exception
        if ($response->getStatusCode() >= 500) {
            $log = is_file($errorLog) ? trim((string) file_get_contents($errorLog)) : '';

            $this->fail(\sprintf("The request \"%s\" failed (HTTP %d).\n%s", $action, $response->getStatusCode(), '' !== $log ? $log : 'No error was logged (is symfony/monolog-bundle installed?).'));
        }

        $json = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        // Only for the tests, not part of the response: all data of the response before it was reduced
        $json['fullData'] = $fullData ?? $json['data'];

        return $json;
    }

    protected function logIn(string $username): FrontendUser
    {
        $user = self::getContainer()->get('contao.security.frontend_user_provider')->loadUserByIdentifier($username);
        self::getContainer()->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'contao_frontend', $user->getRoles()));

        return $user;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function insertRow(string $table, array $row): int
    {
        // Quoted column names: "groups" is a reserved word in MySQL 8
        $quoted = [];

        foreach ($row as $column => $value) {
            $quoted[$this->connection->quoteSingleIdentifier($column)] = $value;
        }

        $this->connection->insert($table, $quoted);

        return (int) $this->connection->lastInsertId();
    }

    protected function createMember(string $username): int
    {
        return $this->insertRow('tl_member', [
            'tstamp' => time(),
            'firstname' => ucfirst($username),
            'lastname' => 'Test',
            'email' => $username.'@example.com',
            'username' => $username,
            'password' => password_hash('test', PASSWORD_DEFAULT),
            'login' => 1,
            'groups' => serialize([]),
        ]);
    }

    /**
     * Root page and regular page (published, language "de").
     */
    protected function createPage(): int
    {
        $root = $this->insertRow('tl_page', ['type' => 'root', 'title' => 'Root', 'alias' => 'root', 'language' => 'de', 'fallback' => 1, 'published' => 1, 'tstamp' => time()]);

        return $this->insertRow('tl_page', ['pid' => $root, 'type' => 'regular', 'title' => 'Buchung', 'alias' => 'buchung', 'published' => 1, 'tstamp' => time()]);
    }

    /**
     * @param list<int>            $resourceTypeIds
     * @param array<string, mixed> $fields          Further fields of tl_module
     */
    protected function createModule(array $resourceTypeIds, array $fields = []): int
    {
        return $this->insertRow('tl_module', [
            'type' => ResourceBookingWeekcalendarController::TYPE,
            'name' => 'Wochenkalender',
            'tstamp' => time(),
            'resourceBooking_resourceTypes' => implode(',', $resourceTypeIds),
            ...$fields,
        ]);
    }

    /**
     * Timetable starting the week on monday with the given time slots (seconds since midnight).
     *
     * @param list<array{0: int, 1: int}> $times
     *
     * @return array{0: int, 1: list<int>} Timetable ID and time slot IDs
     */
    protected function createTimetable(array $times): array
    {
        $timetable = $this->insertRow('tl_resource_booking_time_slot_type', ['title' => 'Stundenplan', 'beginWeek' => 'monday', 'published' => 1, 'tstamp' => time()]);
        $timeSlots = [];

        foreach ($times as $i => [$start, $end]) {
            $timeSlots[] = $this->insertRow('tl_resource_booking_time_slot', [
                'pid' => $timetable,
                'title' => ($i + 1).'. Lektion',
                'sorting' => ($i + 1) * 128,
                'startTime' => $start,
                'endTime' => $end,
                'published' => 1,
                'tstamp' => time(),
            ]);
        }

        return [$timetable, $timeSlots];
    }

    /**
     * @param array<string, mixed> $fields Further fields of tl_resource_booking_resource (e.g. weeksAhead, autoConfirm)
     *
     * @return array{0: int, 1: int} Resource type ID and resource ID
     */
    protected function createResource(int $timetable, int $itemsAvailable = 1, array $fields = []): array
    {
        $type = $this->insertRow('tl_resource_booking_resource_type', ['title' => 'Räume', 'published' => 1, 'tstamp' => time()]);
        $resource = $this->insertRow('tl_resource_booking_resource', [
            'pid' => $type,
            'title' => 'Werkraum',
            'published' => 1,
            'timeSlotType' => $timetable,
            'itemsAvailable' => $itemsAvailable,
            'tstamp' => time(),
            ...$fields,
        ]);

        return [$type, $resource];
    }

    /**
     * Booking of a member (confirmed).
     *
     * @param array<string, mixed> $fields Further fields of tl_resource_booking
     */
    protected function insertBookingRow(int $resource, int $member, int $timeSlot, int $startTime, int $endTime, array $fields = []): int
    {
        return $this->insertRow('tl_resource_booking', [
            'pid' => $resource,
            'member' => $member,
            'timeSlotId' => $timeSlot,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'itemsBooked' => 1,
            'bookingUuid' => uniqid('booking', true),
            'title' => 'Buchung',
            'confirmed' => 1,
            'tstamp' => time(),
            ...$fields,
        ]);
    }

    /**
     * Value of a selected slot of a booking request: "timeSlotId-startTime-endTime-weekStart".
     */
    protected static function slotValue(int $timeSlot, int $startTime, int $endTime): string
    {
        return \sprintf('%d-%d-%d-%d', $timeSlot, $startTime, $endTime, strtotime('monday this week', $startTime));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchBookings(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM tl_resource_booking ORDER BY startTime, id');
    }

    /**
     * Deletes the compiled container of the test kernel once per test run. The container is
     * cached in the temporary directory and is otherwise reused even if services have been
     * removed or renamed in the meantime ("Class … not found" in the cached container).
     */
    private static function clearKernelCache(): void
    {
        if (self::$kernelCacheCleared) {
            return;
        }

        (new Filesystem())->remove(sys_get_temp_dir().'/rbb-integration-tests/var/cache');
        self::$kernelCacheCleared = true;
    }

    /**
     * Forgets the data of previous requests: Contao model registry, preloaded slot data
     * (time slots, bookings, member) and the cached blocked periods. In production the
     * kernel resets the services after every request; the logged-in member is kept.
     */
    private static function resetRequestState(): void
    {
        Registry::getInstance()->reset();
        ResourceBookingBlockedPeriodModel::resetCache();

        $container = self::getContainer();

        // Private service, available in the test container (kernel.reset)
        if ($container->has(SlotDataProvider::class)) {
            $container->get(SlotDataProvider::class)->reset();
        }
    }

    private static function getDatabaseUrl(): string|null
    {
        $url = $_SERVER[self::DATABASE_URL_VARIABLE] ?? $_ENV[self::DATABASE_URL_VARIABLE] ?? getenv(self::DATABASE_URL_VARIABLE);

        return \is_string($url) && '' !== $url ? $url : null;
    }
}
