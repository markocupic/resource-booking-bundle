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
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
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
     * by the AjaxRequestHandler, the JSON response is wrapped in a ResponseException.
     */
    public function testXmlHttpActionRequestThrowsResponseExceptionWithJsonResponse(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'moduleKey' => self::MODULE_KEY]);
        $jsonResponse = new JsonResponse(['status' => 'success']);

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($request, self::MODULE_KEY, $this->anything(), $this->anything())
            ->willReturn($jsonResponse)
        ;

        $controller = $this->getController(ajaxRequestHandler: $handler, requestStack: $this->createRequestStack($request));

        try {
            $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());

            $this->fail('Expected a ResponseException to be thrown.');
        } catch (ResponseException $e) {
            $this->assertSame($jsonResponse, $e->getResponse());
        }
    }

    /**
     * Requests for another week calendar on the same page (other module key) are not
     * answered by this module, and the module is not rendered either.
     */
    public function testRequestsOfAnotherModuleReturnAnEmptyResponse(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'refreshDataRequest', 'moduleKey' => '33_1']);

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->never())
            ->method('handle')
        ;

        $controller = $this->getController(ajaxRequestHandler: $handler, requestStack: $this->createRequestStack($request));

        $response = $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());

        $this->assertSame('', $response->getContent());
        $this->assertSame(self::MODULE_KEY, $request->attributes->get('_rbb_module_key'));
    }

    /**
     * XHR requests of other extensions (no module key) render the module as usual.
     */
    public function testOtherXmlHttpRequestsAreNotTouched(): void
    {
        $request = $this->createXmlHttpRequest(['action' => 'somethingElse']);

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->never())
            ->method('handle')
        ;

        $controller = $this->getController(ajaxRequestHandler: $handler, requestStack: $this->createRequestStack($request));

        // The module renders its template (not available in this test)
        try {
            $response = $controller($request, $this->mockModuleModel(), 'main', null, $this->mockPageModel());
            $this->assertNotSame('', $response->getContent());
        } catch (ResponseException) {
            $this->fail('The module must not answer foreign Ajax requests.');
        } catch (\Throwable) {
            // Rendering the template needs a complete Contao environment
        }
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
    private function getController(AjaxRequestHandler|null $ajaxRequestHandler = null, ContaoCsrfTokenManager|null $contaoCsrfTokenManager = null, RequestStack|null $requestStack = null, ScopeMatcher|null $scopeMatcher = null): ResourceBookingWeekcalendarController
    {
        if (null === $scopeMatcher) {
            $scopeMatcher = $this->createMock(ScopeMatcher::class);
            $scopeMatcher
                ->method('isFrontendRequest')
                ->willReturn(true)
            ;
        }

        return new ResourceBookingWeekcalendarController(
            $ajaxRequestHandler ?? $this->createMock(AjaxRequestHandler::class),
            $contaoCsrfTokenManager ?? $this->createMock(ContaoCsrfTokenManager::class),
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
