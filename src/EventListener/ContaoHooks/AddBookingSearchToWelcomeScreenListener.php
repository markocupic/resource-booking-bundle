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

namespace Markocupic\ResourceBookingBundle\EventListener\ContaoHooks;

use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Backend\BookingSearch;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Search for the bookings of a booking uuid on the back end start page (be_welcome).
 * The hits link directly to the booking. No core template is overridden.
 */
#[AsHook('parseBackendTemplate')]
class AddBookingSearchToWelcomeScreenListener
{
    public const QUERY_PARAMETER = 'rbb_booking_uuid';

    // Back end module of the bookings (see $GLOBALS['BE_MOD']['resourceBookingTool'])
    public const BACKEND_MODULE = 'resource';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly BookingSearch $bookingSearch,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        private readonly Environment $twig,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function __invoke(string $buffer, string $template): string
    {
        if ('be_welcome' !== $template || !$this->canSearch()) {
            return $buffer;
        }

        $request = $this->requestStack->getCurrentRequest();
        $term = trim((string) $request?->query->get(self::QUERY_PARAMETER, ''));

        $searchBox = $this->twig->render('@Contao/backend/rbb_booking_search.html.twig', [
            'action' => $this->router->generate('contao_backend'),
            'parameter' => self::QUERY_PARAMETER,
            'term' => $term,
            'min_length' => BookingSearch::MIN_LENGTH,
            'searched' => '' !== $term,
            'valid' => null !== BookingSearch::normalize($term),
            'result' => '' !== $term ? $this->getResult($term, (string) $request?->attributes->get('_contao_referer_id', '')) : null,
        ]);

        // Insert the search box as first element of the overview
        $count = 0;
        $buffer = preg_replace_callback(
            '/<div id="tl_soverview"[^>]*>/',
            static fn (array $matches): string => $matches[0]."\n".$searchBox,
            $buffer,
            1,
            $count,
        );

        // Fallback, if a custom be_welcome template does not contain #tl_soverview
        return $count > 0 ? (string) $buffer : $searchBox.$buffer;
    }

    /**
     * Administrators and users with access to the back end module of the bookings.
     */
    public function canSearch(): bool
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN')
            || $this->authorizationChecker->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::BACKEND_MODULE);
    }

    /**
     * @return array{total: int, bookings: list<array<string, mixed>>, editIcon: string, listIcon: string}
     */
    private function getResult(string $term, string $refererId): array
    {
        $result = $this->bookingSearch->search($term);

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $datimFormat = (string) $this->framework->getAdapter(Config::class)->get('datimFormat');
        $timeFormat = (string) $this->framework->getAdapter(Config::class)->get('timeFormat');
        $stringUtil = $this->framework->getAdapter(StringUtil::class);
        $requestToken = $this->csrfTokenManager->getDefaultTokenValue();

        $urlParams = static fn (array $params): array => array_filter([...$params, 'rt' => $requestToken, 'ref' => $refererId]);

        foreach ($result['bookings'] as $i => $booking) {
            // Values in the database are encoded (Contao input encoding), Twig encodes them again
            $name = $stringUtil->decodeEntities(trim($booking['memberFirstname'].' '.$booking['memberLastname']));

            $result['bookings'][$i] = [
                ...$booking,
                'resourceTitle' => $stringUtil->decodeEntities((string) $booking['resourceTitle']),
                'resourceTypeTitle' => $stringUtil->decodeEntities((string) $booking['resourceTypeTitle']),
                'date' => $dateAdapter->parse($datimFormat, (int) $booking['startTime']).' – '.$dateAdapter->parse($timeFormat, (int) $booking['endTime']),
                'memberName' => '' !== $name ? $name : null,
                'editUrl' => $this->router->generate('contao_backend', $urlParams([
                    'do' => self::BACKEND_MODULE,
                    'table' => 'tl_resource_booking',
                    'id' => (int) $booking['id'],
                    'act' => 'edit',
                ])),
                // All bookings of the resource
                'listUrl' => $this->router->generate('contao_backend', $urlParams([
                    'do' => self::BACKEND_MODULE,
                    'table' => 'tl_resource_booking',
                    'id' => (int) $booking['pid'],
                ])),
            ];
        }

        return [
            ...$result,
            'editIcon' => $this->framework->getAdapter(Image::class)->getPath('edit.svg'),
            'listIcon' => $this->framework->getAdapter(Image::class)->getPath('children.svg'),
        ];
    }
}
