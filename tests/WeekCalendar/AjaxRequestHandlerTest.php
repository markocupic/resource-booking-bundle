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

namespace Markocupic\ResourceBookingBundle\Tests\WeekCalendar;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AjaxController\AjaxControllerRegistry;
use Markocupic\ResourceBookingBundle\AjaxController\ControllerInterface;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Event\AjaxResponseEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AjaxRequestHandlerTest extends ContaoTestCase
{
    private const MODULE_KEY = '33_0';

    /**
     * The selection sent by the front end is validated by the AppStateFactory and
     * attached to the request; the controller registered for the action answers
     * with a non-cacheable JSON response.
     */
    public function testCallsTheControllerOfTheActionAndReturnsAPrivateJsonResponse(): void
    {
        $request = $this->createXmlHttpRequest(['resType' => '3', 'res' => '7', 'date' => '1795046400']);
        $appState = $this->createAppState();

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), ['resType' => '3', 'res' => '7', 'date' => '1795046400'], false)
            ->willReturn($appState)
        ;

        $stateOfController = null;

        $controllers = [
            'refreshDataRequest' => $this->createController(
                static function (Request $request, AjaxResponse $ajaxResponse) use (&$stateOfController): AjaxResponse {
                    $stateOfController = AppState::fromRequest($request);
                    $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

                    return $ajaxResponse;
                },
            ),
        ];

        $response = $this->getHandler($controllers, $appStateFactory)->handle($request, 'refreshDataRequest', $this->createContext());

        $this->assertSame($appState, $stateOfController);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $this->decode($response)['status']);
        $this->assertSame('refreshDataRequest', $this->decode($response)['action']);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    /**
     * GET requests (calendar) send the selection in the query string.
     */
    public function testGetRequestsReadTheSelectionFromTheQueryString(): void
    {
        $request = Request::create('https://example.com/_rbb/api/calendar', 'GET', ['resType' => '3', 'res' => '7', 'date' => '2026-11-20', 'initial' => '1']);

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), ['resType' => '3', 'res' => '7', 'date' => '2026-11-20'], true)
            ->willReturn($this->createAppState())
        ;

        $response = $this->getHandler(appStateFactory: $appStateFactory)->handle($request, 'refreshDataRequest', $this->createContext());

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Other extensions can add data to the response of every action.
     */
    public function testListenersCanAddDataToTheResponse(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxResponseEvent::class,
            static function (AjaxResponseEvent $event): void {
                if ('refreshDataRequest' === $event->getAction() && '33_0' === $event->getAppState()->moduleKey) {
                    $event->getAjaxResponse()->setData('notice', 'Bitte Zimmer nach Gebrauch lüften.');
                }
            },
        );

        $response = $this->getHandler(eventDispatcher: $eventDispatcher)
            ->handle($this->createXmlHttpRequest([]), 'refreshDataRequest', $this->createContext())
        ;

        $this->assertSame('Bitte Zimmer nach Gebrauch lüften.', $this->decode($response)['data']['notice']);
    }

    /**
     * The event is only dispatched if the controller has answered successfully.
     */
    public function testTheEventIsNotDispatchedIfTheControllerFails(): void
    {
        $controllers = [
            'bookingRequest' => $this->createController(static fn () => throw new \RuntimeException('Database is gone')),
        ];

        $dispatched = false;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxResponseEvent::class,
            static function () use (&$dispatched): void {
                $dispatched = true;
            },
        );

        $response = $this->getHandler($controllers, eventDispatcher: $eventDispatcher)
            ->handle($this->createXmlHttpRequest([]), 'bookingRequest', $this->createContext())
        ;

        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse($dispatched);
    }

    public function testTheFirstRequestOfThePageSelectsAResourceAutomatically(): void
    {
        $request = $this->createXmlHttpRequest(['initial' => '1']);

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), $this->anything(), true)
            ->willReturn($this->createAppState())
        ;

        $response = $this->getHandler(appStateFactory: $appStateFactory)->handle($request, 'refreshDataRequest', $this->createContext());

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * An action without controller is rejected before the AppState is built.
     */
    public function testAnUnknownActionIsAnsweredWithABadRequest(): void
    {
        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->never())
            ->method('create')
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('error')
        ;

        $response = $this->getHandler(appStateFactory: $appStateFactory, logger: $logger)
            ->handle($this->createXmlHttpRequest([]), 'fooRequest', $this->createContext())
        ;

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('error', $this->decode($response)['status']);
        $this->assertSame('RBB.ERR.invalidAjaxAction', $this->decode($response)['messages']['error']);
        $this->assertSame('fooRequest', $this->decode($response)['action']);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testOtherErrorsAreLoggedAndAnsweredWithJson(): void
    {
        $controllers = [
            'bookingRequest' => $this->createController(static fn () => throw new \RuntimeException('Database is gone')),
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Database is gone'))
        ;

        $response = $this->getHandler($controllers, logger: $logger)
            ->handle($this->createXmlHttpRequest([]), 'bookingRequest', $this->createContext())
        ;

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $this->decode($response)['status']);
        $this->assertSame('RBB.ERR.somethingWentWrong', $this->decode($response)['messages']['error']);
        $this->assertSame('bookingRequest', $this->decode($response)['action']);
    }

    public function testContaoResponseExceptionsArePassedThrough(): void
    {
        $controllers = [
            'bookingRequest' => $this->createController(static fn () => throw new RedirectResponseException('https://example.com/login')),
        ];

        $this->expectException(ResponseException::class);

        $this->getHandler($controllers)
            ->handle($this->createXmlHttpRequest([]), 'bookingRequest', $this->createContext())
        ;
    }

    /**
     * @param array<string, ControllerInterface>|null $controllers Default: a refreshDataRequest controller that answers with "success"
     */
    private function getHandler(array|null $controllers = null, AppStateFactory|null $appStateFactory = null, LoggerInterface|null $logger = null, EventDispatcher|null $eventDispatcher = null): AjaxRequestHandler
    {
        $controllers ??= [
            'refreshDataRequest' => $this->createController(
                static function (Request $request, AjaxResponse $ajaxResponse): AjaxResponse {
                    $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);

                    return $ajaxResponse;
                },
            ),
        ];

        if (null === $appStateFactory) {
            $appStateFactory = $this->createMock(AppStateFactory::class);
            $appStateFactory
                ->method('create')
                ->willReturn($this->createAppState())
            ;
        }

        // Returns the translation key, the texts are not loaded in this test
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        $factories = array_map(static fn (ControllerInterface $controller): \Closure => static fn (): ControllerInterface => $controller, $controllers);

        return new AjaxRequestHandler(
            new AjaxControllerRegistry(new ServiceLocator($factories)),
            new AjaxResponseFactory(),
            $appStateFactory,
            $eventDispatcher ?? new EventDispatcher(),
            $translator,
            $logger,
        );
    }

    /**
     * @param \Closure(Request, AjaxResponse): AjaxResponse $generateResponse
     */
    private function createController(\Closure $generateResponse): ControllerInterface
    {
        return new class($generateResponse) implements ControllerInterface {
            public function __construct(private readonly \Closure $generateResponse)
            {
            }

            public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
            {
                return ($this->generateResponse)($request, $ajaxResponse);
            }
        };
    }

    /**
     * @param array<string, string> $post
     */
    private function createXmlHttpRequest(array $post = []): Request
    {
        return Request::create('https://example.com/_rbb/api/bookings', 'POST', $post, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function createContext(): ApiContext
    {
        return new ApiContext($this->mockModuleModel(), $this->mockPageModel(), self::MODULE_KEY);
    }

    private function createAppState(): AppState
    {
        return new AppState(
            self::MODULE_KEY,
            $this->mockModuleModel(),
            $this->mockPageModel(),
            null,
            null,
            ['beginWeek' => 'monday', 'autoSelectFirstResource' => true, 'permittedFormFields' => []],
            0,
            0,
            0,
            'de',
        );
    }

    private function mockModuleModel(): ModuleModel
    {
        return $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33]);
    }

    private function mockPageModel(): PageModel
    {
        return $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]);
    }
}
