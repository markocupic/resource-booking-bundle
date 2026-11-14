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

namespace Markocupic\ResourceBookingBundle\Util;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingTimeSlotTypeModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

class Utils
{
    private SessionBagInterface|null $session = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        RequestStack $requestStack,
        #[Autowire('%markocupic_resource_booking.session.attribute_bag_name%')]
        private readonly string $bagName,
        #[Autowire('%markocupic_resource_booking.apps%')]
        private readonly array $appConfigs = [],
        #[Autowire('%markocupic_resource_booking.displayable_member_fields%')]
        private readonly array $displayableMemberFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_MEMBER_FIELDS,
        #[Autowire('%markocupic_resource_booking.displayable_booking_fields%')]
        private readonly array $displayableBookingFields = RbbConfig::RBB_DEFAULT_DISPLAYABLE_BOOKING_FIELDS,
    ) {
        // Get session from request
        if (null !== ($request = $requestStack->getCurrentRequest())) {
            $this->session = $request->getSession()->getBag($bagName);
        }
    }

    /**
     * @throws \Exception
     */
    public function getModuleModel(): ModuleModel|null
    {
        return $this->framework
            ->getAdapter(ModuleModel::class)
            ->findById($this->session->get('moduleModelId'))
        ;
    }

    /**
     * @throws \Exception
     */
    public function checkMandatoryFieldsSet(array $arrData, string $strTable): array|bool
    {
        $this->framework
            ->getAdapter(Controller::class)
            ->loadDataContainer($strTable)
        ;

        if (empty($GLOBALS['TL_DCA'][$strTable])) {
            throw new \Exception('Data container array for table '.$strTable.' not found.');
        }

        $dca = $GLOBALS['TL_DCA'][$strTable]['fields'];

        foreach ($dca as $fieldName => $fieldConfig) {
            if (isset($fieldConfig['eval']['mandatory']) && true === $fieldConfig['eval']['mandatory']) {
                if (empty($arrData[$fieldName])) {
                    return [$strTable, $fieldName];
                }
            }
        }

        return true;
    }

    /**
     * Returns the app configuration selected in the frontend module
     * (tl_module.resourceBooking_appConfig), completed by the first day of the
     * week of the active resource's timetable (tl_resource_booking_time_slot_type.beginWeek).
     *
     * @return array{beginWeek: string, autoSelectFirstResource: bool, permittedFormFields: list<string>}
     *
     * @throws \Exception
     */
    public function getAppConfig(): array
    {
        $appConfigName = null;

        if (null !== $this->session && $this->session->has('moduleModelId')) {
            $appConfigName = $this->getModuleModel()?->resourceBooking_appConfig;
        }

        return [
            'beginWeek' => $this->getBeginWeek(),
            ...$this->getAppConfigByName(\is_string($appConfigName) ? $appConfigName : null),
        ];
    }

    /**
     * Returns the fields of tl_member that are selected in the frontend module
     * and permitted by markocupic_resource_booking.displayable_member_fields.
     * Only these may be sent to the front end.
     *
     * @return list<string>
     */
    public function getDisplayedMemberFields(ModuleModel $module): array
    {
        if (!$module->resourceBooking_displayClientPersonalData) {
            return [];
        }

        $selected = $this->framework->getAdapter(StringUtil::class)->deserialize($module->resourceBooking_clientPersonalData, true);
        $permitted = array_diff($this->displayableMemberFields, RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS);

        return array_values(array_filter($selected, static fn (mixed $field): bool => \is_string($field) && \in_array($field, $permitted, true)));
    }

    /**
     * Returns the fields of tl_resource_booking that are selected in the frontend
     * module and permitted by markocupic_resource_booking.displayable_booking_fields.
     * Only these may be sent to the front end (in addition to RbbConfig::RBB_ALWAYS_SENT_BOOKING_FIELDS).
     *
     * @return list<string>
     */
    public function getDisplayedBookingFields(ModuleModel $module): array
    {
        if (!$module->resourceBooking_displayBookingDetails) {
            return [];
        }

        $selected = $this->framework->getAdapter(StringUtil::class)->deserialize($module->resourceBooking_bookingDetailFields, true);

        return array_values(array_filter($selected, fn (mixed $field): bool => \is_string($field) && \in_array($field, $this->displayableBookingFields, true)));
    }

    /**
     * Returns the weekday on which the week calendar starts. It is set in the
     * timetable of the resource (tl_resource_booking_time_slot_type.beginWeek).
     * Without a resource, the active resource from the session is used. If
     * there is none, the week starts on monday.
     */
    public function getBeginWeek(ResourceBookingResourceModel|null $resource = null): string
    {
        $resource ??= $this->getActiveResource();

        if (null === $resource || (int) $resource->timeSlotType <= 0) {
            return RbbConfig::RBB_DEFAULT_BEGIN_WEEK;
        }

        $timeSlotType = $this->framework
            ->getAdapter(ResourceBookingTimeSlotTypeModel::class)
            ->findById((int) $resource->timeSlotType)
        ;

        return ResourceBookingRestriction::getBeginWeek($timeSlotType);
    }

    /**
     * Returns the app configuration with the given name. If it does not exist,
     * the "default" app configuration or, if there is none, the defaults apply.
     *
     * @return array{autoSelectFirstResource: bool, permittedFormFields: list<string>}
     */
    public function getAppConfigByName(string|null $appConfigName): array
    {
        $appConfig = $this->appConfigs[(string) $appConfigName] ?? $this->appConfigs['default'] ?? [];

        return [
            // Display the first available resource if the session is empty
            'autoSelectFirstResource' => (bool) ($appConfig['auto_select_first_resource'] ?? true),
            'permittedFormFields' => array_values(array_unique([...RbbConfig::RBB_BASE_PERMITTED_FORM_FIELDS, ...($appConfig['permitted_form_fields'] ?? [])])),
        ];
    }

    private function getActiveResource(): ResourceBookingResourceModel|null
    {
        $resourceId = (int) ($this->session?->get('res', 0) ?? 0);

        if ($resourceId <= 0) {
            return null;
        }

        return $this->framework
            ->getAdapter(ResourceBookingResourceModel::class)
            ->findById($resourceId)
        ;
    }
}
