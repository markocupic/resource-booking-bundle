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
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Exception\UnknownAjaxActionException;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class AjaxRequestHandlerTest extends ContaoTestCase
{
    private const MODULE_KEY = '33_0';

    /**
     * The selection sent by the front end is validated by the AppStateFactory and
     * attached to the request; the answer is a non-cacheable JSON response.
     */
    public function testDispatchesTheEventAndReturnsAPrivateJsonResponse(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'resType' => '3', 'res' => '7', 'date' => '1795046400']);
        $appState = $this->createAppState();

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), ['resType' => '3', 'res' => '7', 'date' => '1795046400'], false)
            ->willReturn($appState)
        ;

        $stateOfEvent = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxRequestEvent::class,
            static function (AjaxRequestEvent $event) use (&$stateOfEvent): void {
                $stateOfEvent = AppState::fromRequest($event->getRequest());
                $event->getAjaxResponse()->setStatus('success');
            },
        );

        $response = $this->getHandler($appStateFactory, $eventDispatcher)->handle($request, self::MODULE_KEY, $this->mockModuleModel(), $this->mockPageModel());

        $this->assertSame($appState, $stateOfEvent);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $this->decode($response)['status']);
        $this->assertSame('refreshDataRequest', $this->decode($response)['action']);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testTheFirstRequestOfThePageSelectsAResourceAutomatically(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'initial' => '1']);

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), $this->anything(), true)
            ->willReturn($this->createAppState())
        ;

        $response = $this->getHandler($appStateFactory)->handle($request, self::MODULE_KEY, $this->mockModuleModel(), $this->mockPageModel());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAnUnknownActionIsAnsweredWithABadRequest(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxRequestEvent::class,
            static fn () => throw new UnknownAjaxActionException('Could not find Controller for action "foo".'),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->never())
            ->method('error')
        ;

        $response = $this->getHandler(eventDispatcher: $eventDispatcher, logger: $logger)
            ->handle($this->createXmlHttpRequest(['action' => 'fooRequest']), self::MODULE_KEY, $this->mockModuleModel(), $this->mockPageModel())
        ;

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('error', $this->decode($response)['status']);
        $this->assertSame('RBB.ERR.invalidAjaxAction', $this->decode($response)['messages']['error']);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testOtherErrorsAreLoggedAndAnsweredWithJson(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxRequestEvent::class,
            static fn () => throw new \RuntimeException('Database is gone'),
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Database is gone'))
        ;

        $response = $this->getHandler(eventDispatcher: $eventDispatcher, logger: $logger)
            ->handle($this->createXmlHttpRequest(['action' => 'bookingRequest']), self::MODULE_KEY, $this->mockModuleModel(), $this->mockPageModel())
        ;

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $this->decode($response)['status']);
        $this->assertSame('RBB.ERR.somethingWentWrong', $this->decode($response)['messages']['error']);
        $this->assertSame('bookingRequest', $this->decode($response)['action']);
    }

    public function testContaoResponseExceptionsArePassedThrough(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            AjaxRequestEvent::class,
            static fn () => throw new RedirectResponseException('https://example.com/login'),
        );

        $this->expectException(ResponseException::class);

        $this->getHandler(eventDispatcher: $eventDispatcher)
            ->handle($this->createXmlHttpRequest(['action' => 'bookingRequest']), self::MODULE_KEY, $this->mockModuleModel(), $this->mockPageModel())
        ;
    }

    private function getHandler(AppStateFactory|null $appStateFactory = null, EventDispatcher|null $eventDispatcher = null, LoggerInterface|null $logger = null): AjaxRequestHandler
    {
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

        return new AjaxRequestHandler(
            new AjaxResponseFactory(),
            $appStateFactory,
            $eventDispatcher ?? new EventDispatcher(),
            $translator,
            $logger,
        );
    }

    /**
     * @param array<string, string> $post
     */
    private function createXmlHttpRequest(array $post): Request
    {
        return Request::create('https://example.com/calendar', 'POST', $post, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
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
