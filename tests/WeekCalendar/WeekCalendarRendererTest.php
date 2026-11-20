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

use Contao\ModuleModel;
use Contao\PageModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\AppState\AppConfig;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Markocupic\ResourceBookingBundle\WeekCalendar\WeekCalendarRenderer;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class WeekCalendarRendererTest extends ContaoTestCase
{
    public function testRendersTheWeekViewBlockOfTheModuleTemplate(): void
    {
        $html = $this->getRenderer()->render($this->getAppState(''), ['rows' => ['a', 'b']]);

        // The nested blocks of the partials (block() in a used template) are found
        $this->assertSame('[33_0|https://example.com/calendar|<a><b>]', $html);
    }

    public function testAVariantOfTheModuleTemplateOverridesTheBlocks(): void
    {
        $html = $this->getRenderer()->render($this->getAppState('frontend_module/resource_booking_weekcalendar/my_variant'), ['rows' => ['a']]);

        $this->assertSame('[33_0|https://example.com/calendar|(a)]', $html);
    }

    public function testOnlyVariantsOfTheWeekCalendarTemplateAreUsed(): void
    {
        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['customTpl' => 'frontend_module/other']);

        $this->assertSame('@Contao/frontend_module/resource_booking_weekcalendar.html.twig', WeekCalendarRenderer::getTemplateName($module));

        $module = $this->createClassWithPropertiesStub(ModuleModel::class, ['customTpl' => 'frontend_module/resource_booking_weekcalendar/my_variant']);

        $this->assertSame('@Contao/frontend_module/resource_booking_weekcalendar/my_variant.html.twig', WeekCalendarRenderer::getTemplateName($module));
    }

    public function testThePageUrlIsEmptyIfItCannotBeGenerated(): void
    {
        $page = $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]);
        $page
            ->method('getAbsoluteUrl')
            ->willThrowException(new \RuntimeException('No route'))
        ;

        $html = $this->getRenderer()->render($this->getAppState('', $page), ['rows' => []]);

        $this->assertSame('[33_0||]', $html);
    }

    private function getRenderer(): WeekCalendarRenderer
    {
        $loader = new ArrayLoader([
            '@Contao/frontend_module/_base.html.twig' => '<div>{% block content %}{% endblock %}</div>',
            '@Contao/frontend_module/resource_booking_weekcalendar.html.twig' => "{% extends '@Contao/frontend_module/_base.html.twig' %}{% use '@Rbb/_view.html.twig' %}{% use '@Rbb/_cell.html.twig' %}{% block content %}page{% endblock %}",
            '@Contao/frontend_module/resource_booking_weekcalendar/my_variant.html.twig' => "{% extends '@Contao/frontend_module/resource_booking_weekcalendar.html.twig' %}{% block rbb_cell %}({{ row }}){% endblock %}",
            '@Rbb/_view.html.twig' => "{% block rbb_week_calendar_view %}[{{ moduleKey }}|{{ pageUrl }}|{% for row in rows %}{{ block('rbb_cell') }}{% endfor %}]{% endblock %}",
            '@Rbb/_cell.html.twig' => '{% block rbb_cell %}<{{ row }}>{% endblock %}',
        ]);

        return new WeekCalendarRenderer(new Environment($loader, ['strict_variables' => true]));
    }

    private function getAppState(string $customTemplate, PageModel|null $page = null): AppState
    {
        if (null === $page) {
            $page = $this->createClassWithPropertiesStub(PageModel::class, ['id' => 5]);
            $page
                ->method('getAbsoluteUrl')
                ->willReturn('https://example.com/calendar')
            ;
        }

        return new AppState(
            '33_0',
            $this->createClassWithPropertiesStub(ModuleModel::class, ['id' => 33, 'customTpl' => $customTemplate]),
            $page,
            null,
            null,
            new AppConfig(),
            'monday',
            0,
            0,
            0,
            'de',
        );
    }
}
