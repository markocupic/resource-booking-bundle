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

namespace Markocupic\ResourceBookingBundle\Tests\Controller\Api;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Api\AccessChecker;
use Markocupic\ResourceBookingBundle\Api\AccessTokenManager;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\Api\InvalidAccessTokenException;
use Markocupic\ResourceBookingBundle\Controller\Api\WeekCalendarApiController;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;

class WeekCalendarApiControllerTest extends ContaoTestCase
{
    protected function tearDown(): void
    {
        // Set by the controller (page context)
        unset($GLOBALS['objPage']);

        parent::tearDown();
    }

    public function testTheRoutesAreInTheFrontendScope(): void
    {
        $route = (new \ReflectionClass(WeekCalendarApiController::class))->getAttributes(Route::class)[0]->newInstance();

        $this->assertSame('/_rbb/api', $route->path);
        $this->assertSame('frontend', $route->defaults['_scope']);
    }

    public function testTheCalendarIsAnsweredWithAValidToken(): void
    {
        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(Request::class), 'refreshDataRequest', $this->isInstanceOf(ApiContext::class))
            ->willReturn(new JsonResponse(['status' => 'success']))
        ;

        $request = $this->createApiRequest('GET');
        $response = $this->getController($handler)->calendar($request);

        $this->assertSame(200, $response->getStatusCode());

        // The page context is provided as on the page itself
        $this->assertInstanceOf(PageModel::class, $request->attributes->get('pageModel'));
        $this->assertSame('de', $request->getLocale());
    }

    public function testRequestsWithoutValidTokenAreAnsweredWith401(): void
    {
        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->never())
            ->method('handle')
        ;

        $handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with('refreshDataRequest', 'RBB.ERR.accessTokenExpired', 401)
            ->willReturn(new JsonResponse(['status' => 'error'], 401))
        ;

        $request = Request::create('https://example.com/_rbb/api/calendar');
        $request->headers->set(AccessTokenManager::HEADER, 'manipulated');

        $this->assertSame(401, $this->getController($handler)->calendar($request)->getStatusCode());
    }

    public function testDeniedAccessIsAnsweredWith403(): void
    {
        $checker = $this->createStub(AccessChecker::class);
        $checker
            ->method('check')
            ->willThrowException(new AccessDeniedHttpException('Page ID 5 is protected.'))
        ;

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->never())
            ->method('handle')
        ;

        $handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with('bookingRequest', 'RBB.ERR.accessDenied', 403)
            ->willReturn(new JsonResponse(['status' => 'error'], 403))
        ;

        $this->assertSame(403, $this->getController($handler, $checker)->createBooking($this->createApiRequest('POST', csrfToken: 'valid'))->getStatusCode());
    }

    /**
     * A changed member (logout, other member) is treated like an expired token.
     */
    public function testAChangedMemberIsAnsweredWith401(): void
    {
        $checker = $this->createStub(AccessChecker::class);
        $checker
            ->method('check')
            ->willThrowException(new InvalidAccessTokenException('The logged-in member has changed.'))
        ;

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with('refreshDataRequest', 'RBB.ERR.accessTokenExpired', 401)
            ->willReturn(new JsonResponse(['status' => 'error'], 401))
        ;

        $this->assertSame(401, $this->getController($handler, $checker)->calendar($this->createApiRequest('GET'))->getStatusCode());
    }

    /**
     * Write requests need the CSRF token of Contao.
     */
    public function testWriteRequestsNeedAValidCsrfToken(): void
    {
        foreach ([null, 'invalid'] as $csrfToken) {
            $handler = $this->createMock(AjaxRequestHandler::class);
            $handler
                ->expects($this->never())
                ->method('handle')
            ;

            $handler
                ->expects($this->once())
                ->method('createErrorResponse')
                ->with('bookingRequest', 'RBB.ERR.accessDenied', 403)
                ->willReturn(new JsonResponse(['status' => 'error'], 403))
            ;

            $this->assertSame(403, $this->getController($handler)->createBooking($this->createApiRequest('POST', csrfToken: $csrfToken))->getStatusCode());
        }
    }

    public function testBookingsCanBeCreatedValidatedAndCancelled(): void
    {
        foreach (['createBooking' => ['POST', 'bookingRequest'], 'validateBooking' => ['POST', 'bookingFormValidationRequest'], 'cancelBooking' => ['DELETE', 'cancelBookingRequest']] as $method => [$httpMethod, $action]) {
            $handler = $this->createMock(AjaxRequestHandler::class);
            $handler
                ->expects($this->once())
                ->method('handle')
                ->with($this->anything(), $action, $this->anything())
                ->willReturn(new JsonResponse(['status' => 'success']))
            ;

            $this->assertSame(200, $this->getController($handler)->{$method}($this->createApiRequest($httpMethod, csrfToken: 'valid'))->getStatusCode());
        }
    }

    /**
     * Actions of other extensions get a generic route, the actions of the bundle not.
     */
    public function testCustomActions(): void
    {
        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->with($this->anything(), 'myRequest', $this->anything())
            ->willReturn(new JsonResponse(['status' => 'success']))
        ;

        $handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with('bookingRequest', 'RBB.ERR.invalidAjaxAction', 404)
            ->willReturn(new JsonResponse(['status' => 'error'], 404))
        ;

        $controller = $this->getController($handler);

        $this->assertSame(200, $controller->action($this->createApiRequest('POST', csrfToken: 'valid'), 'myRequest')->getStatusCode());
        $this->assertSame(404, $controller->action($this->createApiRequest('POST', csrfToken: 'valid'), 'bookingRequest')->getStatusCode());
    }

    private function getController(AjaxRequestHandler $handler, AccessChecker|null $checker = null): WeekCalendarApiController
    {
        if (null === $checker) {
            $checker = $this->createStub(AccessChecker::class);
            $checker
                ->method('check')
                ->willReturn(new ApiContext(
                    $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33]),
                    $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5, 'rootLanguage' => 'de', 'language' => 'de']),
                    '33_0',
                ))
            ;
        }

        $csrfTokenManager = $this->createStub(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('isTokenValid')
            ->willReturnCallback(static fn (CsrfToken $token): bool => 'contao_csrf_token' === $token->getId() && 'valid' === $token->getValue())
        ;

        return new WeekCalendarApiController(
            new AccessTokenManager('secret', new MockClock()),
            $checker,
            $handler,
            $csrfTokenManager,
            $this->createStub(TranslatorInterface::class),
            'contao_csrf_token',
        );
    }

    private function createApiRequest(string $method, string|null $csrfToken = null): Request
    {
        $request = Request::create('https://example.com/_rbb/api/calendar', $method);
        $request->headers->set(AccessTokenManager::HEADER, (new AccessTokenManager('secret', new MockClock()))->create(33, 5, '33_0', null));

        if (null !== $csrfToken) {
            $request->headers->set(WeekCalendarApiController::CSRF_HEADER, $csrfToken);
        }

        return $request;
    }
}
