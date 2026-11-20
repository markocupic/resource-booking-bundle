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

namespace Markocupic\ResourceBookingBundle\Twig;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Config\RbbConfig;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Data of the booking person (tl_member) for templates, independent of the fields selected in the
 * module: {{ rbb_booking_member(booking).email|default }}.
 *
 * The data is only available on the server (rendered HTML), it is not part of the JSON of the API.
 * Whatever the template displays is visible to all logged-in members: display only what they may see.
 * For guests (not logged in) the function returns null, the fields RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS
 * (password etc.) are never returned.
 */
class BookingMemberExtension extends AbstractExtension implements ResetInterface
{
    /**
     * @var array<int, array<string, string>|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rbb_booking_member', $this->getBookingMember(...)),
        ];
    }

    /**
     * @param array<string, mixed>|int $booking A booking of the template (booking.id) or the ID of the booking
     *
     * @return array<string, string>|null Row of tl_member, null if the member does not exist or nobody is logged in
     */
    public function getBookingMember(array|int $booking): array|null
    {
        if (!$this->security->getUser() instanceof FrontendUser) {
            return null;
        }

        $bookingId = (int) (\is_array($booking) ? ($booking['id'] ?? 0) : $booking);

        if ($bookingId < 1) {
            return null;
        }

        if (\array_key_exists($bookingId, $this->cache)) {
            return $this->cache[$bookingId];
        }

        $this->framework->initialize();

        $bookingModel = $this->framework->getAdapter(ResourceBookingModel::class)->findById($bookingId);
        $member = null === $bookingModel ? null : $this->framework->getAdapter(MemberModel::class)->findById((int) $bookingModel->member);

        if (null === $member) {
            return $this->cache[$bookingId] = null;
        }

        $data = [];

        foreach ($member->row() as $field => $value) {
            if (\in_array($field, RbbConfig::RBB_FORBIDDEN_MEMBER_FIELDS, true) || (null !== $value && !\is_scalar($value))) {
                continue;
            }

            $data[$field] = StringUtil::decodeEntities((string) $value);
        }

        return $this->cache[$bookingId] = $data;
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
