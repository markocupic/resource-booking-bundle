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

namespace Markocupic\ResourceBookingBundle\Tests\NotificationType;

use Contao\TestCase\ContaoTestCase;
use Markocupic\ResourceBookingBundle\NotificationType\AbstractResourceBookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\BookingNotificationType;
use Markocupic\ResourceBookingBundle\NotificationType\CancellationNotificationType;
use PHPUnit\Framework\Attributes\DataProvider;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\TokenDefinitionInterface;

class NotificationTypeTest extends ContaoTestCase
{
    public function testNames(): void
    {
        $factory = $this->createStub(TokenDefinitionFactoryInterface::class);

        $this->assertSame('resource-booking-notification', (new BookingNotificationType($factory))->getName());
        $this->assertSame('resource-booking-cancellation-notification', (new CancellationNotificationType($factory))->getName());
    }

    /**
     * @param class-string<AbstractResourceBookingNotificationType> $notificationTypeClass
     */
    #[DataProvider('notificationTypeProvider')]
    public function testTokenDefinitions(string $notificationTypeClass, string $name): void
    {
        $created = [];

        $factory = $this->createStub(TokenDefinitionFactoryInterface::class);
        $factory
            ->method('create')
            ->willReturnCallback(
                function (string $definitionClass, string $tokenName, string $translationKey) use (&$created): TokenDefinitionInterface {
                    $created[$tokenName] = [$definitionClass, $translationKey];

                    return $this->createStub(TokenDefinitionInterface::class);
                },
            )
        ;

        $definitions = (new $notificationTypeClass($factory))->getTokenDefinitions();

        $this->assertCount(\count($created), $definitions);

        // The email token is also suggested in the recipient fields.
        $this->assertSame([EmailTokenDefinition::class, $name.'.booking_person_email'], $created['booking_person_email']);

        // All other tokens are text tokens (subject and text fields), including the wildcards.
        foreach (['booking_details', 'booking_datim', 'booking_person_*', 'booking_resource_*', 'booking_resource_type_*', 'booking_*'] as $token) {
            $this->assertSame([TextTokenDefinition::class, $name.'.'.$token], $created[$token]);
        }
    }

    public static function notificationTypeProvider(): iterable
    {
        yield [BookingNotificationType::class, BookingNotificationType::NAME];
        yield [CancellationNotificationType::class, CancellationNotificationType::NAME];
    }

    public function testEveryTokenHasADescription(): void
    {
        $tokens = [...AbstractResourceBookingNotificationType::getTokenConfig()['email_token'], ...AbstractResourceBookingNotificationType::getTokenConfig()['text_token']];

        foreach (['de', 'en'] as $language) {
            $GLOBALS['TL_LANG'] = [];
            include __DIR__.'/../../contao/languages/'.$language.'/nc_tokens.php';

            foreach ([BookingNotificationType::NAME, CancellationNotificationType::NAME] as $type) {
                foreach ($tokens as $token) {
                    $this->assertNotEmpty($GLOBALS['TL_LANG']['nc_tokens'][$type][$token] ?? null, \sprintf('Missing description for "%s" (%s, %s).', $token, $type, $language));
                }
            }
        }

        unset($GLOBALS['TL_LANG']);
    }
}
