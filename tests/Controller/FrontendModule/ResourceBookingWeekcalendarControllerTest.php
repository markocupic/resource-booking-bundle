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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AjaxController\RefreshDataController;
use Markocupic\ResourceBookingBundle\Api\AccessTokenManager;
use Markocupic\ResourceBookingBundle\Api\ApiContext;
use Markocupic\ResourceBookingBundle\AppState\ModuleKeyGenerator;
use Markocupic\ResourceBookingBundle\Controller\FrontendModule\ResourceBookingWeekcalendarController;
use Markocupic\ResourceBookingBundle\EventListener\RenderWeekCalendarListener;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Markocupic\ResourceBookingBundle\WeekCalendar\AjaxRequestHandler;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResourceBookingWeekcalendarControllerTest extends ContaoTestCase
{
    private const MODULE_ID = 33;

    private const PAGE_ID = 5;

    public function testIsAFrontendModuleController(): void
    {
        $this->assertInstanceOf(AbstractFrontendModuleController::class, $this->getController());
        $this->assertSame('resourceBookingWeekcalendar', ResourceBookingWeekcalendarController::TYPE);
    }

    /**
     * getResponse() passes the module key, the CSRF token, the access token and the
     * URLs of the API to the template and excludes the calendar from the search index.
     */
    public function testGetResponseAssignsTheTemplateData(): void
    {
        $csrfTokenManager = $this->createStub(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('csrf-token-value')
        ;

        $tokenManager = new AccessTokenManager('secret', new MockClock());
        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response('rendered'));
        $request = $this->createRequest();

        $this->invokeProtected($this->getController($csrfTokenManager, $tokenManager, memberId: 7, requestStack: $this->createRequestStack($request)), 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $data = $template->getData();

        $this->assertSame('33_0', $data['moduleKey']);
        $this->assertSame('csrf-token-value', $data['csrfToken']);
        $this->assertFalse($data['searchable']);
        $this->assertSame('', $data['colorScheme']);
        $this->assertSame(['calendar' => '/route/markocupic_resource_booking_api_calendar', 'validateBooking' => '/route/markocupic_resource_booking_api_validate_booking', 'bookings' => '/route/markocupic_resource_booking_api_create_booking', 'action' => '/route/markocupic_resource_booking_api_action'], $data['apiUrls']);

        // The token names module, page, module key and member
        $token = $tokenManager->parse($data['accessToken']);

        $this->assertSame(self::MODULE_ID, $token->moduleId);
        $this->assertSame(self::PAGE_ID, $token->pageId);
        $this->assertSame('33_0', $token->moduleKey);
        $this->assertSame(7, $token->memberId);
    }

    /**
     * Every page load gets a new token.
     */
    public function testEveryPageLoadGetsANewToken(): void
    {
        $tokens = [];

        foreach ([1, 2] as $i) {
            $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response());
            $request = $this->createRequest();

            $this->invokeProtected($this->getController(requestStack: $this->createRequestStack($request)), 'getResponse', [$template, $this->mockModuleModel(), $request]);

            $tokens[] = $template->getData()['accessToken'];
        }

        $this->assertNotSame($tokens[0], $tokens[1]);
    }

    /**
     * The page contains the access token: it must never be stored in a shared cache.
     */
    public function testTheResponseIsPrivateAndNotStored(): void
    {
        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response('rendered'));
        $request = $this->createRequest();

        $response = $this->invokeProtected($this->getController(requestStack: $this->createRequestStack($request)), 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testGuestsGetATokenWithoutMember(): void
    {
        $tokenManager = new AccessTokenManager('secret', new MockClock());
        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response());
        $request = $this->createRequest();

        $this->invokeProtected($this->getController(accessTokenManager: $tokenManager, requestStack: $this->createRequestStack($request)), 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $this->assertNull($tokenManager->parse($template->getData()['accessToken'])->memberId);
    }

    /**
     * The color scheme of the module settings (resourceBooking_colorScheme): light, dark or automatic ('').
     */
    public function testGetResponseAssignsTheColorScheme(): void
    {
        foreach (['dark' => 'dark', 'light' => 'light', '' => '', 'foo' => ''] as $value => $expected) {
            $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response());
            $model = $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => self::MODULE_ID, 'resourceBooking_colorScheme' => $value]);
            $request = $this->createRequest();

            $this->invokeProtected($this->getController(requestStack: $this->createRequestStack($request)), 'getResponse', [$template, $model, $request]);

            $this->assertSame($expected, $template->getData()['colorScheme']);
        }
    }

    /**
     * The week calendar is rendered with the page: same request as the API (refreshDataRequest),
     * with the selection of the query string.
     */
    public function testRendersTheWeekCalendarWithThePage(): void
    {
        $calendar = new AjaxResponse(RefreshDataController::ACTION);
        $calendar->setData(RenderWeekCalendarListener::DATA_KEY, '<div>week</div>');
        $calendar->setInfoMessage('Select a resource');

        $handler = $this->createMock(AjaxRequestHandler::class);
        $handler
            ->expects($this->once())
            ->method('process')
            ->with(
                $this->callback(static fn (Request $request): bool => ['initial' => '1', 'resType' => '4', 'res' => '9', 'date' => '2026-11-23'] === $request->query->all() && 'de' === $request->getLocale()),
                RefreshDataController::ACTION,
                $this->callback(static fn (ApiContext $context): bool => '33_0' === $context->moduleKey && self::PAGE_ID === (int) $context->page->id),
            )
            ->willReturn($calendar)
        ;

        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response());
        $request = $this->createRequest('?resType=4&res=9&date=2026-11-23&foo[]=bar');
        $request->setLocale('de');

        $this->invokeProtected($this->getController(requestStack: $this->createRequestStack($request), ajaxRequestHandler: $handler), 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $this->assertSame('<div>week</div>', $template->getData()['weekCalendarHtml']);
        $this->assertSame('Select a resource', $template->getData()['weekCalendarMessages']['info']);
    }

    /**
     * If the week calendar cannot be rendered, the front end loads it.
     */
    public function testAnErrorIsLoggedAndTheWeekCalendarIsLoadedByTheFrontEnd(): void
    {
        $handler = $this->createStub(AjaxRequestHandler::class);
        $handler
            ->method('process')
            ->willThrowException(new \RuntimeException('Database not available'))
        ;

        $template = new FragmentTemplate('frontend_module/resource_booking_weekcalendar', static fn (): Response => new Response());
        $request = $this->createRequest();

        $this->invokeProtected($this->getController(requestStack: $this->createRequestStack($request), ajaxRequestHandler: $handler), 'getResponse', [$template, $this->mockModuleModel(), $request]);

        $this->assertSame('', $template->getData()['weekCalendarHtml']);
        $this->assertSame([], $template->getData()['weekCalendarMessages']);
    }

    public function testUsesTheModernTemplate(): void
    {
        $attribute = (new \ReflectionClass(ResourceBookingWeekcalendarController::class))->getAttributes(AsFrontendModule::class)[0]->newInstance();

        $this->assertSame('frontend_module/resource_booking_weekcalendar', $attribute->attributes['template']);
    }

    private function getController(ContaoCsrfTokenManager|null $contaoCsrfTokenManager = null, AccessTokenManager|null $accessTokenManager = null, int|null $memberId = null, RequestStack|null $requestStack = null, AjaxRequestHandler|null $ajaxRequestHandler = null): ResourceBookingWeekcalendarController
    {
        if (null === $ajaxRequestHandler) {
            $calendar = new AjaxResponse(RefreshDataController::ACTION);
            $calendar->setData(RenderWeekCalendarListener::DATA_KEY, '<div>week</div>');

            $ajaxRequestHandler = $this->createStub(AjaxRequestHandler::class);
            $ajaxRequestHandler
                ->method('process')
                ->willReturn($calendar)
            ;
        }

        $loggedInUser = $this->createStub(LoggedInFrontendUser::class);
        $loggedInUser
            ->method('getLoggedInUser')
            ->willReturn(null === $memberId ? null : $this->createClassWithPropertiesStub(FrontendUser::class, ['id' => $memberId]))
        ;

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturnCallback(static fn (string $name): string => '/route/'.$name)
        ;

        return new ResourceBookingWeekcalendarController(
            $accessTokenManager ?? new AccessTokenManager('secret', new MockClock()),
            $contaoCsrfTokenManager ?? $this->createStub(ContaoCsrfTokenManager::class),
            $loggedInUser,
            new ModuleKeyGenerator(),
            $requestStack ?? new RequestStack(),
            $urlGenerator,
            $ajaxRequestHandler,
        );
    }

    /**
     * Request of the module with the page (set by __invoke()).
     */
    private function createRequest(string $queryString = ''): Request
    {
        $request = Request::create('https://example.com/calendar'.$queryString);
        $request->attributes->set('_rbb_page', $this->createClassWithPropertiesStub(PageModel::class, ['id' => self::PAGE_ID]));

        return $request;
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

    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        return (new \ReflectionMethod($object, $method))->invokeArgs($object, $args);
    }
}
