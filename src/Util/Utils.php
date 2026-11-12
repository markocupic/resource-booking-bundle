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
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
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
     * (tl_module.resourceBooking_appConfig). The number of weeks back/ahead
     * and the auto confirmation are no longer part of it, but are set per resource.
     *
     * @return array{beginWeek: string, permittedFormFields: list<string>}
     *
     * @throws \Exception
     */
    public function getAppConfig(): array
    {
        $appConfigName = null;

        if (null !== $this->session && $this->session->has('moduleModelId')) {
            $appConfigName = $this->getModuleModel()?->resourceBooking_appConfig;
        }

        return $this->getAppConfigByName(\is_string($appConfigName) ? $appConfigName : null);
    }

    /**
     * Returns the app configuration with the given name. If it does not exist,
     * the "default" app configuration or, if there is none, the defaults apply.
     *
     * @return array{beginWeek: string, permittedFormFields: list<string>}
     */
    public function getAppConfigByName(string|null $appConfigName): array
    {
        $appConfig = $this->appConfigs[(string) $appConfigName] ?? $this->appConfigs['default'] ?? [];

        return [
            'beginWeek' => (string) ($appConfig['begin_week'] ?? 'monday'),
            'permittedFormFields' => array_values(array_unique([...RbbConfig::RBB_BASE_PERMITTED_FORM_FIELDS, ...($appConfig['permitted_form_fields'] ?? [])])),
        ];
    }
}
