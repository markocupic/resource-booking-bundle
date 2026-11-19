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

namespace Markocupic\ResourceBookingBundle\AppState;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\LocaleUtil;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Util\CsvList;
use Markocupic\ResourceBookingBundle\Util\DateHelper;
use Markocupic\ResourceBookingBundle\Util\WeekBoundaries;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Creates the AppState of a request from the selection sent by the front end.
 *
 * The values come from the browser and are therefore validated: only published
 * resource types selected in the module and their published resources are
 * accepted, invalid values are ignored (fall back to "nothing selected"), and the
 * displayed week is moved into the permitted weeks of the resource.
 */
class AppStateFactory
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly AppConfigResolver $appConfigResolver,
        private readonly WeekBoundaries $weekBoundaries,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * @param array<string, mixed> $input      Selection of the front end: resType, res and date (timestamp of a day of the displayed week)
     * @param bool                 $autoSelect Select a resource automatically if nothing is selected (first request of a page)
     *
     * @throws \Exception
     */
    public function create(string $moduleKey, ModuleModel $module, PageModel $page, array $input, bool $autoSelect, string $locale, int|null $now = null): AppState
    {
        $now ??= $this->clock->now()->getTimestamp();

        [$resourceType, $resource] = $this->resolveSelection($module, self::toInt($input['resType'] ?? null), self::toInt($input['res'] ?? null), $autoSelect);

        $beginWeek = $this->appConfigResolver->getBeginWeek($resource);

        [$activeWeek, $firstWeek, $lastWeek] = $this->resolveWeeks($beginWeek, $resource, self::toTimestamp($input['date'] ?? null), $now);

        return new AppState(
            $moduleKey,
            $module,
            $page,
            $resourceType,
            $resource,
            $this->appConfigResolver->getAppConfig($module),
            $beginWeek,
            $activeWeek,
            $firstWeek,
            $lastWeek,
            LocaleUtil::formatAsLanguageTag($locale),
        );
    }

    /**
     * @return array{0: ResourceBookingResourceTypeModel|null, 1: ResourceBookingResourceModel|null}
     */
    public function resolveSelection(ModuleModel $module, int $resourceTypeId, int $resourceId, bool $autoSelect): array
    {
        $resourceTypeAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);
        $resourceAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);

        $moduleResourceTypeIds = CsvList::parseIds($module->resourceBooking_resourceTypes);

        $resourceType = null;
        $resource = null;

        // A resource determines its resource type (e.g. a direct link with ?res=…), unless
        // another resource type has been selected (the resource is then reset).
        if ($resourceId > 0 && null !== ($candidate = $resourceAdapter->findPublishedByPk($resourceId))) {
            $candidateTypeId = (int) $candidate->pid;

            if (\in_array($candidateTypeId, $moduleResourceTypeIds, true) && ($resourceTypeId <= 0 || $resourceTypeId === $candidateTypeId)) {
                $resourceType = $resourceTypeAdapter->findPublishedByPk($candidateTypeId);
                $resource = null === $resourceType ? null : $candidate;
            }
        }

        if (null === $resourceType && $resourceTypeId > 0 && \in_array($resourceTypeId, $moduleResourceTypeIds, true)) {
            $resourceType = $resourceTypeAdapter->findPublishedByPk($resourceTypeId);
        }

        if (!$autoSelect) {
            return [$resourceType, $resource];
        }

        // Nothing selected: display the first available resource (can be disabled in the app configuration)
        if (null === $resourceType && $this->appConfigResolver->getAppConfig($module)->autoSelectFirstResource) {
            foreach ($resourceTypeAdapter->findPublishedByIds($moduleResourceTypeIds) ?? [] as $candidateType) {
                $resources = $resourceAdapter->findPublishedByPid((int) $candidateType->id);

                if (null !== $resources && $resources->count() > 0) {
                    return [$candidateType, $resources->first()->current()];
                }
            }
        }

        // Only one resource type in the filter menu: select it
        if (null === $resourceType) {
            $resourceTypes = $resourceTypeAdapter->findPublishedByIds($moduleResourceTypeIds);

            if (null !== $resourceTypes && 1 === $resourceTypes->count()) {
                $resourceType = $resourceTypes->first()->current();
            }
        }

        // Only one resource of the resource type: select it
        if (null !== $resourceType && null === $resource) {
            $resources = $resourceAdapter->findPublishedByPid((int) $resourceType->id);

            if (null !== $resources && 1 === $resources->count()) {
                $resource = $resources->first()->current();
            }
        }

        return [$resourceType, $resource];
    }

    /**
     * Returns the displayed week and the first and last permitted week. The weeks
     * start with the first day of the week of the timetable of the resource.
     *
     * @param string $beginWeek First day of the week of the timetable, e.g. "monday"
     *
     * @return array{0: int, 1: int, 2: int}
     *
     * @throws \Exception
     */
    public function resolveWeeks(string $beginWeek, ResourceBookingResourceModel|null $resource, int $date, int $now): array
    {
        $firstWeek = $this->weekBoundaries->getFirstPermittedWeek($beginWeek, $resource, $now);
        $lastWeek = $this->weekBoundaries->getLastPermittedWeek($beginWeek, $resource, $now);

        // Without a date the current week is displayed. Otherwise, the date is moved to the first
        // day of its week (the timetables of the resources may start the week on other weekdays).
        $activeWeek = DateHelper::getFirstDayOfWeek($beginWeek, $date > 0 ? $date : $now);
        $activeWeek = min(max($activeWeek, $firstWeek), $lastWeek);

        return [(int) $activeWeek, (int) $firstWeek, (int) $lastWeek];
    }

    /**
     * Timestamp of the front end or a date of a direct link (e.g. ?date=2026-11-20).
     */
    private static function toTimestamp(mixed $value): int
    {
        if (\is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return false !== $date && $date->format('Y-m-d') === $value ? (int) strtotime($value) : 0;
        }

        return self::toInt($value);
    }

    /**
     * Values of the front end, e.g. "12", "" or "undefined" (nothing selected in Vue).
     */
    private static function toInt(mixed $value): int
    {
        return \is_int($value) || (\is_string($value) && preg_match('/^\d+$/', $value)) ? (int) $value : 0;
    }
}
