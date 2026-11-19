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

namespace Markocupic\ResourceBookingBundle\Tests\EventListener\ContaoHooks;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Backend\BookingSearch;
use Markocupic\ResourceBookingBundle\EventListener\ContaoHooks\AddBookingSearchToWelcomeScreenListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

class AddBookingSearchToWelcomeScreenListenerTest extends ContaoTestCase
{
    private const WELCOME = '<div id="tl_soverview">'."\n".'<div id="tl_messages"></div></div>';

    public function testIgnoresOtherTemplates(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->never())
            ->method('render')
        ;

        $listener = $this->getListener(twig: $twig);

        $this->assertSame('<p>buffer</p>', $listener('<p>buffer</p>', 'be_main'));
    }

    public function testIsOnlyShownToUsersWithAccessToTheBookings(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->never())
            ->method('render')
        ;

        $listener = $this->getListener(isAdmin: false, hasModuleAccess: false, twig: $twig);

        $this->assertSame(self::WELCOME, $listener(self::WELCOME, 'be_welcome'));
        $this->assertFalse($listener->canSearch());
    }

    public function testUsersWithAccessToTheModuleCanSearch(): void
    {
        $this->assertTrue($this->getListener(isAdmin: false, hasModuleAccess: true)->canSearch());
        $this->assertTrue($this->getListener(isAdmin: true, hasModuleAccess: false)->canSearch());
    }

    public function testInsertsTheSearchBoxAtTheTopOfTheOverview(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->with(
                '@Contao/backend/rbb_booking_search.html.twig',
                $this->callback(static fn (array $context): bool => '' === $context['term'] && false === $context['searched'] && null === $context['result']),
            )
            ->willReturn('<div id="tl_rbb_booking_search"></div>')
        ;

        $listener = $this->getListener(twig: $twig);

        $this->assertSame(
            '<div id="tl_soverview">'."\n".'<div id="tl_rbb_booking_search"></div>'."\n".'<div id="tl_messages"></div></div>',
            $listener(self::WELCOME, 'be_welcome'),
        );
    }

    public function testPrependsTheSearchBoxIfTheOverviewIsMissing(): void
    {
        $twig = $this->createStub(Environment::class);
        $twig
            ->method('render')
            ->willReturn('<search>')
        ;

        $this->assertSame('<search><p>custom</p>', $this->getListener(twig: $twig)('<p>custom</p>', 'be_welcome'));
    }

    public function testAddsTheLinksToTheBookings(): void
    {
        $request = Request::create('https://example.com/contao', 'GET', [AddBookingSearchToWelcomeScreenListener::QUERY_PARAMETER => '0f8f2c4e']);
        $request->attributes->set('_contao_referer_id', 'ref123');

        $bookingSearch = $this->createMock(BookingSearch::class);
        $bookingSearch
            ->expects($this->once())
            ->method('search')
            ->with('0f8f2c4e')
            ->willReturn(['total' => 1, 'bookings' => [[
                'id' => 12,
                'pid' => 3,
                'startTime' => 1795420800,
                'endTime' => 1795424400,
                'memberFirstname' => 'Marko',
                'memberLastname' => 'Cupic',
                'resourceTitle' => 'Forsthaus &amp; Garten',
                'resourceTypeTitle' => 'Räume',
            ]]])
        ;

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static fn (string $name, array $params = []): string => '/contao?'.http_build_query($params))
        ;

        $context = null;

        $twig = $this->createStub(Environment::class);
        $twig
            ->method('render')
            ->willReturnCallback(
                static function (string $name, array $ctx) use (&$context): string {
                    $context = $ctx;

                    return '';
                },
            )
        ;

        $listener = $this->getListener(twig: $twig, request: $request, bookingSearch: $bookingSearch, router: $router);
        $listener(self::WELCOME, 'be_welcome');

        $this->assertTrue($context['searched']);
        $this->assertTrue($context['valid']);
        $this->assertSame(1, $context['result']['total']);

        $booking = $context['result']['bookings'][0];

        $this->assertSame('/contao?do=resource&table=tl_resource_booking&id=12&act=edit&rt=token&ref=ref123', $booking['editUrl']);
        $this->assertSame('/contao?do=resource&table=tl_resource_booking&id=3&rt=token&ref=ref123', $booking['listUrl']);
        $this->assertSame('Marko Cupic', $booking['memberName']);
        $this->assertSame('Forsthaus & Garten', $booking['resourceTitle']);
        $this->assertSame('d1795420800 – t1795424400', $booking['date']);
    }

    private function getListener(bool $isAdmin = true, bool $hasModuleAccess = true, Environment|null $twig = null, Request|null $request = null, BookingSearch|null $bookingSearch = null, UrlGeneratorInterface|null $router = null): AddBookingSearchToWelcomeScreenListener
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->method('isGranted')
            ->willReturnCallback(
                static fn (string $attribute, mixed $subject = null): bool => match ($attribute) {
                    'ROLE_ADMIN' => $isAdmin,
                    ContaoCorePermissions::USER_CAN_ACCESS_MODULE => $hasModuleAccess && 'resource' === $subject,
                    default => false,
                },
            )
        ;

        $csrfTokenManager = $this->createStub(ContaoCsrfTokenManager::class);
        $csrfTokenManager
            ->method('getDefaultTokenValue')
            ->willReturn('token')
        ;

        $configAdapter = $this->createAdapterStub(['get']);
        $configAdapter
            ->method('get')
            ->willReturnMap([['datimFormat', 'd.m.Y H:i'], ['timeFormat', 'H:i']])
        ;

        $dateAdapter = $this->createAdapterStub(['parse']);
        $dateAdapter
            ->method('parse')
            ->willReturnCallback(static fn (string $format, int $tstamp): string => ('H:i' === $format ? 't' : 'd').$tstamp)
        ;

        $imageAdapter = $this->createAdapterStub(['getPath']);
        $imageAdapter
            ->method('getPath')
            ->willReturnArgument(0)
        ;

        $stringUtilAdapter = $this->createAdapterStub(['decodeEntities']);
        $stringUtilAdapter
            ->method('decodeEntities')
            ->willReturnCallback(static fn (string $value): string => html_entity_decode($value))
        ;

        $framework = $this->createContaoFrameworkStub([
            Config::class => $configAdapter,
            Date::class => $dateAdapter,
            Image::class => $imageAdapter,
            StringUtil::class => $stringUtilAdapter,
        ]);

        $requestStack = new RequestStack();
        $requestStack->push($request ?? Request::create('https://example.com/contao'));

        if (null === $router) {
            $router = $this->createStub(UrlGeneratorInterface::class);
            $router
                ->method('generate')
                ->willReturn('/contao')
            ;
        }

        return new AddBookingSearchToWelcomeScreenListener(
            $authorizationChecker,
            $bookingSearch ?? $this->createStub(BookingSearch::class),
            $csrfTokenManager,
            $framework,
            $twig ?? $this->createStub(Environment::class),
            $requestStack,
            $router,
        );
    }
}
