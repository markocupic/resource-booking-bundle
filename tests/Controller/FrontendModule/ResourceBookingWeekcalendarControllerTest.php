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
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleIndex;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Markocupic\ResourceBookingBundle\AppInitialization\Initialize;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\Response\AjaxResponseFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class ResourceBookingWeekcalendarControllerTest extends ContaoTestCase
{
    private const MODULE_ID = 33;

    private const PAGE_ID = 5;

    // With a module index of 0 the controller builds "<moduleId>_<moduleIndex>".
    private const MODULE_KEY = '33_0';

    public function testIsAFrontendModuleController(): void
    {
        $controller = $this->getController();

        $this->assertInstanceOf(AbstractFrontendModuleController::class, $controller);
        $this->assertSame('resourceBookingWeekcalendar', ResourceBookingWeekcalendarController::TYPE);
    }

    /**
     * An XHR request without the "token_<moduleKey>" query parameter must be
     * answered with an empty "204 No Content" response and must NOT initialize
     * the application.
     */
    public function testXmlHttpRequestWithoutTokenReturnsNoContent(): void
    {
        $request = Request::create(
            'https://example.com/calendar',
            'GET',
            [],
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $appInitializer = $this->createMock(Initialize::class);

        // The application must not be initialized on this early-return path.
        $appInitializer
            ->expects($this->never())
            ->method('initialize')
        ;

        $controller = $this->getController(
            framework: $this->mockFrameworkWithHelperAdapters(),
            requestStack: $requestStack,
            appInitializer: $appInitializer,
        );

        $response = $controller(
            $request,
            $this->mockModuleModel(),
            'main',
            null,
            $this->mockPageModel(),
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    /**
     * A non-XHR request without a token must generate a token, append it to the
     * URL as "token_<moduleKey>" and issue a redirect to the same URL.
     */
    public function testNonXmlHttpRequestWithoutTokenRedirectsWithGeneratedToken(): void
    {
        $token = 'a1b2c3d4-token';

        $request = Request::create('https://example.com/calendar', 'GET');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controller = $this->getController(
            framework: $this->mockFrameworkWithHelperAdapters($token),
            requestStack: $requestStack,
        );

        $response = $controller(
            $request,
            $this->mockModuleModel(),
            'main',
            null,
            $this->mockPageModel(),
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('token_'.self::MODULE_KEY.'='.$token, $response->getTargetUrl());
    }

    /**
     * An XHR request that carries a valid token, an "action" and a matching
     * "moduleKey" must be answered by throwing a ResponseException that wraps a
     * JSON response with the correct (non-cacheable) headers.
     */
    public function testXmlHttpActionRequestThrowsResponseExceptionWithJsonResponse(): void
    {
        $request = Request::create(
            'https://example.com/calendar?token_'.self::MODULE_KEY.'=valid-token',
            'POST',
            ['action' => 'showBookingForm', 'moduleKey' => self::MODULE_KEY],
            [],
            [],
            ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $appInitializer = $this->createMock(Initialize::class);
        $appInitializer
            ->expects($this->once())
            ->method('initialize')
            ->with(self::MODULE_ID, self::PAGE_ID)
        ;

        $controller = $this->getController(
            framework: $this->mockFrameworkWithHelperAdapters(),
            requestStack: $requestStack,
            appInitializer: $appInitializer,
            ajaxResponseFactory: new AjaxResponseFactory(),
            eventDispatcher: new EventDispatcher(),
        );

        try {
            $controller(
                $request,
                $this->mockModuleModel(),
                'main',
                null,
                $this->mockPageModel(),
            );

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
    }

    /**
     * getResponse() must expose the module key and the CSRF token to the
     * template and return the template's response.
     */
    public function testGetResponseAssignsModuleKeyAndCsrfTokenToTemplate(): void
    {
        $expectedResponse = new Response('rendered');

        $csrfTokenManager = $this->createMock(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('csrf-token-value')
        ;

        $framework = $this->mockContaoFramework([
            ModuleKey::class => $this->mockAdapter(['getModuleKey']),
        ]);

        $framework->getAdapter(ModuleKey::class)
            ->method('getModuleKey')
            ->willReturn(self::MODULE_KEY)
        ;

        // PHPUnit 12 removed withConsecutive(); record the __set() calls and
        // assert on them afterwards instead.
        $assignedTemplateVars = [];

        $template = $this->createMock(Template::class);
        $template
            ->method('__set')
            ->willReturnCallback(
                static function (string $key, mixed $value) use (&$assignedTemplateVars): void {
                    $assignedTemplateVars[$key] = $value;
                },
            )
        ;
        $template
            ->method('getResponse')
            ->willReturn($expectedResponse)
        ;

        $controller = $this->getController(
            framework: $framework,
            contaoCsrfTokenManager: $csrfTokenManager,
        );

        $response = $this->invokeProtected(
            $controller,
            'getResponse',
            [$template, $this->mockModuleModel(), Request::create('https://example.com/calendar')],
        );

        $this->assertSame($expectedResponse, $response);
        $this->assertSame(
            [
                'moduleKey' => self::MODULE_KEY,
                'csrfToken' => 'csrf-token-value',
            ],
            $assignedTemplateVars,
        );
    }

    /**
     * Builds the controller with sensible mock defaults; every collaborator can
     * be overridden per test.
     */
    private function getController(ContaoFramework|null $framework = null, ContaoCsrfTokenManager|null $contaoCsrfTokenManager = null, AjaxResponseFactory|null $ajaxResponseFactory = null, EventDispatcherInterface|null $eventDispatcher = null, Initialize|null $appInitializer = null, RequestStack|null $requestStack = null, ScopeMatcher|null $scopeMatcher = null): ResourceBookingWeekcalendarController
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
            $contaoCsrfTokenManager ?? $this->createMock(ContaoCsrfTokenManager::class),
            $framework ?? $this->mockContaoFramework(),
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $appInitializer ?? $this->createMock(Initialize::class),
            $requestStack ?? new RequestStack(),
            $scopeMatcher,
        );
    }

    /**
     * Mocks the Contao framework with the three static helper adapters the
     * controller resolves in __invoke().
     */
    private function mockFrameworkWithHelperAdapters(string $token = 'generated-token'): ContaoFramework
    {
        $moduleIndexAdapter = $this->mockAdapter(['generateModuleIndex', 'getModuleIndex']);
        $moduleIndexAdapter
            ->method('getModuleIndex')
            ->willReturn(0)
        ;

        $moduleKeyAdapter = $this->mockAdapter(['setModuleKey', 'getModuleKey']);
        $moduleKeyAdapter
            ->method('getModuleKey')
            ->willReturn(self::MODULE_KEY)
        ;

        $tokenManagerAdapter = $this->mockAdapter(['generateToken', 'getToken']);
        $tokenManagerAdapter
            ->method('getToken')
            ->willReturn($token)
        ;

        return $this->mockContaoFramework([
            ModuleIndex::class => $moduleIndexAdapter,
            ModuleKey::class => $moduleKeyAdapter,
            TokenManager::class => $tokenManagerAdapter,
        ]);
    }

    private function mockModuleModel(): ModuleModel
    {
        return $this->mockClassWithProperties(ModuleModel::class, ['id' => self::MODULE_ID]);
    }

    private function mockPageModel(): PageModel
    {
        return $this->mockClassWithProperties(PageModel::class, ['id' => self::PAGE_ID]);
    }

    /**
     * Calls a protected/private method on the given object.
     */
    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }
}
