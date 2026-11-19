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

/**
 * An app configuration of the week calendar (markocupic_resource_booking.apps),
 * selected in the frontend module (tl_module.resourceBooking_appConfig).
 */
final readonly class AppConfig
{
    /**
     * @param list<string> $permittedFormFields Custom form fields of the booking form; the fields of the
     *                                          bundle are always permitted (Validator\BookingRequestValidator)
     */
    public function __construct(
        public string $name = 'default',
        // Display the first available resource if nothing is selected
        public bool $autoSelectFirstResource = true,
        public array $permittedFormFields = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config A configured app (snake_case keys of the bundle configuration)
     */
    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            $name,
            (bool) ($config['auto_select_first_resource'] ?? true),
            array_values(array_unique(array_map('strval', $config['permitted_form_fields'] ?? []))),
        );
    }
}
