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

namespace Markocupic\ResourceBookingBundle\Tests\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\AppState\AppStateFactory;
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Event\AjaxRequestEvent;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ResourceBookingWeekcalendarControllerTest extends ContaoTestCase
{
    private const MODULE_ID = 33;

    private const PAGE_ID = 5;

    // First week calendar on the page: "<moduleId>_<index>"
    private const MODULE_KEY = '33_0';

    public function testIsAFrontendModuleController(): void
    {
        $controller = $this->getController();

        $this->assertInstanceOf(AbstractFrontendModuleController::class, $controller);
        $this->assertSame('resourceBookingWeekcalendar', ResourceBookingWeekcalendarController::TYPE);
    }

    /**
     * An XHR request with an "action" and the module key of this module is answered
     * with a ResponseException that wraps a non-cacheable JSON response. The selection
     * sent by the front end is validated by the AppStateFactory and attached to the request.
     */
    public function testXmlHttpActionRequestThrowsResponseExceptionWithJsonResponse(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'moduleKey' => self::MODULE_KEY, 'resType' => '3', 'res' => '7', 'date' => '1795046400']);
        $appState = $this->createAppState();

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), ['resType' => '3', 'res' => '7', 'date' => '1795046400'], false)
            ->willReturn($appState)
        ;

        $eventDispatcher = new EventDispatcher();
        $stateOfEvent = null;
        $eventDispatcher->addListener(
            AjaxRequestEvent::class,
            static function (AjaxRequestEvent $event) use (&$stateOfEvent): void {
                $stateOfEvent = AppState::fromRequest($event->getRequest());
            },
        );

        $controller = $this->getController(
            appStateFactory: $appStateFactory,
            requestStack: $this->createRequestStack($request),
            ajaxResponseFactory: new AjaxResponseFactory(),
            eventDispatcher: $eventDispatcher,
        );

        try {
            $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());

            $this->fail('Expected a ResponseException to be thrown.');
        } catch (ResponseException $e) {
            $response = $e->getResponse();

            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame(0, $response->getMaxAge());
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));

            // The response must be private and must not be publicly cacheable.
            $this->assertTrue($response->headers->hasCacheControlDirective('private'));
            $this->assertFalse($response->headers->hasCacheControlDirective('public'));
        }

        $this->assertSame($appState, $stateOfEvent);
    }

    public function testTheFirstRequestOfThePageSelectsAResourceAutomatically(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'moduleKey' => self::MODULE_KEY, 'initial' => '1']);

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->once())
            ->method('create')
            ->with(self::MODULE_KEY, $this->anything(), $this->anything(), $this->anything(), true)
            ->willReturn($this->createAppState())
        ;

        $controller = $this->getController(
            appStateFactory: $appStateFactory,
            requestStack: $this->createRequestStack($request),
            ajaxResponseFactory: new AjaxResponseFactory(),
            eventDispatcher: new EventDispatcher(),
        );

        $this->expectException(ResponseException::class);

        $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());
    }

    /**
     * Requests for another week calendar on the same page (other module key) are not
     * answered by this module.
     */
    public function testRequestsOfAnotherModuleAreIgnored(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'moduleKey' => '33_1']);

        $appStateFactory = $this->createMock(AppStateFactory::class);
        $appStateFactory
            ->expects($this->never())
            ->method('create')
        ;

        $controller = $this->getController(appStateFactory: $appStateFactory, requestStack: $this->createRequestStack($request));

        // The module renders its template instead (not available in this test)
        try {
            $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());
        } catch (ResponseException) {
            $this->fail('The module must not answer requests of another module.');
        } catch (\Throwable) {
            // Rendering the template needs a complete Contao environment
        }

        $this->assertSame(self::MODULE_KEY, $request->attributes->get('_rbb_module_key'));
    }

    /**
     * getResponse() exposes the module key and the CSRF token to the template and
     * excludes the calendar from the search index.
     */
    public function testGetResponseAssignsModuleKeyAndCsrfTokenToTemplate(): void
    {
        $expectedResponse = new Response('rendered');

        $csrfTokenManager = $this->createMock(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('csrf-token-value')
        ;

        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => $expectedResponse);

        $request = Request::create('https://example.com/calendar');
        $request->attributes->set('_rbb_module_key', '33_1');

        $controller = $this->getController(contaoCsrfTokenManager: $csrfTokenManager);

        $response = $this->invokeProtected($controller, 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $this->assertSame($expectedResponse, $response);
        $this->assertSame(['moduleKey' => '33_1', 'csrfToken' => 'csrf-token-value', 'searchable' => false], $template->getData());
    }

    public function testUsesTheModernTemplate(): void
    {
        $attribute = (new \ReflectionClass(ResourceBookingWeekcalendarController::class))->getAttributes(AsFrontendModule::class)[0]->newInstance();

        $this->assertSame('frontend_module/resource_booking_weekcalendar', $attribute->attributes['template']);
    }

    /**
     * Builds the controller with sensible mock defaults; every collaborator can
     * be overridden per test.
     */
    private function getController(AppStateFactory|null $appStateFactory = null, ContaoCsrfTokenManager|null $contaoCsrfTokenManager = null, AjaxResponseFactory|null $ajaxResponseFactory = null, EventDispatcherInterface|null $eventDispatcher = null, RequestStack|null $requestStack = null, ScopeMatcher|null $scopeMatcher = null): ResourceBookingWeekcalendarController
    {
        if (null === $scopeMatcher) {
            $scopeMatcher = $this->createMock(ScopeMatcher::class);
            $scopeMatcher
                ->method('isFrontendRequest')
                ->willReturn(true)
            ;
        }

        return new ResourceBookingWeekcalendarController(
            $ajaxResponseFactory ?? $this->createMock(AjaxResponseFactory::class),
            $appStateFactory ?? $this->createMock(AppStateFactory::class),
            $contaoCsrfTokenManager ?? $this->createMock(ContaoCsrfTokenManager::class),
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            new ModuleKeyGenerator(),
            $requestStack ?? new RequestStack(),
            $scopeMatcher,
        );
    }

    /**
     * @param array<string, string> $post
     */
    private function createXmlHttpRequest(array $post): Request
    {
        return Request::create('https://example.com/calendar', 'POST', $post, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    private function createRequestStack(Request $request): RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
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
        return $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => self::MODULE_ID]);
    }

    private function mockPageModel(): PageModel
    {
        return $this->createClassWithPropertiesStub(PageModel::class, ['id' => self::PAGE_ID]);
    }

    /**
     * Calls a protected/private method on the given object.
     */
    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invokeArgs($object, $args);
    }
}
