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

namespace Markocupic\ResourceBookingBundle\NotificationType;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

/**
 * Booking and cancellation notifications share the same tokens.
 *
 * The token definitions make the tokens available to the auto suggester in
 * the Notification Center backend. Text tokens are suggested in the subject and
 * text fields, email tokens additionally in the recipient fields. The
 * descriptions are translated in contao/languages/<lang>/nc_tokens.php
 * (translation key "nc_tokens.<notification type>.<token>").
 */
abstract class AbstractResourceBookingNotificationType implements NotificationTypeInterface
{
    public function __construct(private readonly TokenDefinitionFactoryInterface $factory)
    {
    }

    public function getTokenDefinitions(): array
    {
        $tokenDefinitions = [];

        foreach (self::getTokenConfig()['text_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(TextTokenDefinition::class, $token, $this->getName().'.'.$token);
        }

        foreach (self::getTokenConfig()['email_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(EmailTokenDefinition::class, $token, $this->getName().'.'.$token);
        }

        return $tokenDefinitions;
    }

    /**
     * @return array{email_token: list<string>, text_token: list<string>}
     */
    public static function getTokenConfig(): array
    {
        return [
            'email_token' => [
                'booking_person_email',
            ],
            'text_token' => [
                // Booking person (tl_member)
                'booking_person_firstname',
                'booking_person_lastname',
                'booking_person_gender',
                'booking_person_phone',
                'booking_person_street',
                'booking_person_postal',
                'booking_person_city',
                'booking_person_*',
                // Booking (tl_resource_booking)
                'booking_details',
                'booking_details_html',
                'booking_description',
                'booking_datim',
                // Resource type (tl_resource_booking_resource_type) before resource, because both share the prefix
                'booking_resource_type_title',
                'booking_resource_type_description',
                'booking_resource_type_*',
                // Resource (tl_resource_booking_resource)
                'booking_resource_title',
                'booking_resource_description',
                'booking_resource_*',
                'booking_*',
            ],
        ];
    }
}
