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
use Contao\ModuleModel;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Markocupic\ResourceBookingBundle\Restriction\TimeSlotRules;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resolves the app configuration of a week calendar module (markocupic_resource_booking.apps,
 * selected in tl_module.resourceBooking_appConfig) and the first day of the week, which is set
 * in the timetable of the resource (tl_resource_booking_time_slot_type.beginWeek).
 */
class AppConfigResolver
{
    /**
     * @param array<string, array<string, mixed>> $appConfigs
     */
    public function __construct(
        private readonly ContaoFramework $framework,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs = [],
    ) {
    }

    /**
     * The app configuration selected in the module.
     */
    public function getAppConfig(ModuleModel|null $module): AppConfig
    {
        $name = $module?->resourceBooking_appConfig;

        return $this->getByName(\is_string($name) && '' !== $name ? $name : null);
    }

    /**
     * The app configuration with the given name. If it does not exist, the "default"
     * app configuration or, if there is none, the defaults apply.
     */
    public function getByName(string|null $name): AppConfig
    {
        if (null !== $name && isset($this->appConfigs[$name])) {
            return AppConfig::fromConfig($name, $this->appConfigs[$name]);
        }

        return AppConfig::fromConfig('default', $this->appConfigs['default'] ?? []);
    }

    /**
     * The weekday on which the week calendar starts (timetable of the resource).
     * Without a resource or timetable, the week starts on monday.
     */
    public function getBeginWeek(ResourceBookingResourceModel|null $resource): string
    {
        if (null === $resource || (int) $resource->timeSlotType <= 0) {
            return RbbConfig::RBB_DEFAULT_BEGIN_WEEK;
        }

        $timetable = $this->framework->getAdapter(ResourceBookingTimeSlotTypeModel::class)->findById((int) $resource->timeSlotType);

        return TimeSlotRules::getBeginWeek($timetable);
    }
}
