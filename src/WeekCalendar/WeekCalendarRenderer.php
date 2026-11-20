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

namespace Markocupic\ResourceBookingBundle\WeekCalendar;

use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\AppState\AppState;
use Twig\Environment;
use Twig\Template;

/**
 * Renders the week view (header, filter, table, legend) and the booking window on the server:
 * blocks of the template of the module (rbb_week_calendar_view, rbb_booking_window_view). A variant of the module template
 * (tl_module.customTpl, e.g. frontend_module/resource_booking_weekcalendar/my_variant) can
 * override every block of the week view.
 */
class WeekCalendarRenderer
{
    public const DEFAULT_TEMPLATE = 'frontend_module/resource_booking_weekcalendar';

    public const BLOCK = 'rbb_week_calendar_view';

    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param array<string, mixed> $data Data of the week calendar (Api\Output\CalendarData::toArray(), changed by listeners)
     */
    public function render(AppState $appState, array $data): string
    {
        return $this->renderBlock($appState, self::BLOCK, $data);
    }

    /**
     * Renders a block of the template of the module, e.g. the booking window (rbb_booking_window_view).
     *
     * @param array<string, mixed> $data
     */
    public function renderBlock(AppState $appState, string $block, array $data): string
    {
        $context = [
            ...$this->twig->getGlobals(),
            ...$data,
            'moduleKey' => $appState->moduleKey,
            'pageUrl' => self::getPageUrl($appState),
        ];

        $template = $this->twig->load(self::getTemplateName($appState->module))->unwrap();

        return $template->renderBlock($block, $context, self::getBlocks($template, $context));
    }

    /**
     * Twig name of the template selected in the module (only variants of the week calendar template).
     */
    public static function getTemplateName(ModuleModel $module): string
    {
        $customTemplate = (string) $module->customTpl;

        if (!str_starts_with($customTemplate, self::DEFAULT_TEMPLATE.'/')) {
            $customTemplate = self::DEFAULT_TEMPLATE;
        }

        return '@Contao/'.$customTemplate.'.html.twig';
    }

    /**
     * Blocks of the template and its parents, the blocks of a variant override the blocks of
     * the parent. TemplateWrapper::renderBlock() would only pass the blocks of the template
     * itself: the nested blocks (block() in the partials) would not be found.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, array{0: Template, 1: string}>
     */
    private static function getBlocks(Template $template, array $context): array
    {
        $chain = [];

        for ($current = $template; $current instanceof Template; $current = $current->getParent($context)) {
            $chain[] = $current;
        }

        $blocks = [];

        foreach (array_reverse($chain) as $current) {
            $blocks = [...$blocks, ...$current->getBlocks()];
        }

        return $blocks;
    }

    /**
     * URL of the page of the week calendar (action of the filter form).
     */
    private static function getPageUrl(AppState $appState): string
    {
        try {
            return $appState->page->getAbsoluteUrl();
        } catch (\Throwable) {
            return '';
        }
    }
}
