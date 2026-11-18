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

namespace Markocupic\ResourceBookingBundle\Tests\Api;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\Api\AccessChecker;
use Markocupic\ResourceBookingBundle\Api\AccessToken;
use Markocupic\ResourceBookingBundle\Api\InvalidAccessTokenException;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AccessCheckerTest extends ContaoTestCase
{
    public function testReturnsModulePageAndModuleKey(): void
    {
        $page = $this->mockPage();
        $module = $this->mockModule();

        $context = $this->getChecker(page: $page, module: $module, memberId: 7)->check($this->token(memberId: 7));

        $this->assertSame($module, $context->module);
        $this->assertSame($page, $context->page);
        $this->assertSame('33_0', $context->moduleKey);
    }

    /**
     * Logged out, logged in or another member since the page was loaded.
     */
    public function testTheMemberMustNotHaveChanged(): void
    {
        foreach ([[7, null], [null, 7], [7, 8]] as [$tokenMember, $currentMember]) {
            try {
                $this->getChecker(memberId: $currentMember)->check($this->token(memberId: $tokenMember));
                $this->fail('Expected an InvalidAccessTokenException.');
            } catch (InvalidAccessTokenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testGuestsAreAllowedOnUnprotectedPages(): void
    {
        $context = $this->getChecker()->check($this->token());

        $this->assertSame('33_0', $context->moduleKey);
    }

    /**
     * Unpublished pages (also start/stop) are not found by findPublishedById().
     */
    public function testThePageMustBePublished(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->getChecker(page: false)->check($this->token());
    }

    public function testProtectedPagesNeedAMemberOfTheGroups(): void
    {
        $page = $this->mockPage(['protected' => '1', 'groups' => [2]]);

        // Member of group 2
        $this->getChecker(page: $page, memberId: 7, granted: true)->check($this->token(memberId: 7));

        // Not a member of group 2
        $this->expectException(AccessDeniedHttpException::class);

        $this->getChecker(page: $page, memberId: 7, granted: false)->check($this->token(memberId: 7));
    }

    public function testGuestsCannotAccessProtectedPages(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->getChecker(page: $this->mockPage(['protected' => '1', 'groups' => [2]]), granted: true)->check($this->token());
    }

    /**
     * Group -1: pages for guests only.
     */
    public function testGuestsCanAccessPagesForGuests(): void
    {
        $context = $this->getChecker(page: $this->mockPage(['protected' => '1', 'groups' => [-1]]), granted: true)->check($this->token());

        $this->assertSame('33_0', $context->moduleKey);
    }

    public function testTheModuleMustExistAndBeAWeekCalendar(): void
    {
        foreach ([false, $this->mockModule('navigation')] as $module) {
            try {
                $this->getChecker(module: $module)->check($this->token());
                $this->fail('Expected an AccessDeniedHttpException.');
            } catch (AccessDeniedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function token(int|null $memberId = null): AccessToken
    {
        return new AccessToken(33, 5, '33_0', $memberId, time() + 3600);
    }

    /**
     * @param PageModel|false|null   $page   false: page not found
     * @param ModuleModel|false|null $module false: module not found
     */
    private function getChecker(PageModel|false|null $page = null, ModuleModel|false|null $module = null, int|null $memberId = null, bool $granted = true): AccessChecker
    {
        $pageAdapter = $this->createAdapterStub(['findPublishedById']);
        $pageAdapter
            ->method('findPublishedById')
            ->willReturn(false === $page ? null : ($page ?? $this->mockPage()))
        ;

        $moduleAdapter = $this->createAdapterStub(['findById']);
        $moduleAdapter
            ->method('findById')
            ->willReturn(false === $module ? null : ($module ?? $this->mockModule()))
        ;

        $framework = $this->createContaoFrameworkStub([
            PageModel::class => $pageAdapter,
            ModuleModel::class => $moduleAdapter,
        ]);

        $loggedInUser = $this->createStub(LoggedInFrontendUser::class);
        $loggedInUser
            ->method('getLoggedInUser')
            ->willReturn(null === $memberId ? null : $this->createClassWithPropertiesStub(FrontendUser::class, ['id' => $memberId]))
        ;

        $security = $this->createStub(Security::class);
        $security
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attribute): bool => ContaoCorePermissions::MEMBER_IN_GROUPS === $attribute && $granted)
        ;

        return new AccessChecker($framework, $loggedInUser, $security);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function mockPage(array $properties = []): PageModel
    {
        return $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5, 'protected' => '', 'groups' => [], ...$properties]);
    }

    private function mockModule(string $type = 'resourceBookingWeekcalendar'): ModuleModel
    {
        return $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33, 'type' => $type]);
    }
}
