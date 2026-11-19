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

namespace Markocupic\ResourceBookingBundle\Api\Input;

use Symfony\Component\HttpFoundation\Request;

/**
 * Selection of the week calendar sent with every request: resource type, resource
 * and week (also the parameters of direct links). The values are checked by the
 * AppStateFactory (published, part of the module, within the permitted weeks).
 *
 * GET and DELETE: query string, POST: request body.
 */
final class CalendarQuery
{
    public function __construct(
        public readonly string|null $resourceTypeId = null,
        public readonly string|null $resourceId = null,
        public readonly string|null $date = null,
        // First request of the page: select a resource automatically if nothing is selected
        public readonly bool $initial = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $input = $request->isMethod('POST') ? $request->request->all() : $request->query->all();

        return new self(
            self::stringOrNull($input['resType'] ?? null),
            self::stringOrNull($input['res'] ?? null),
            self::stringOrNull($input['date'] ?? null),
            '1' === ($input['initial'] ?? null),
        );
    }

    /**
     * @return array{resType: string|null, res: string|null, date: string|null}
     */
    public function toSelection(): array
    {
        return ['resType' => $this->resourceTypeId, 'res' => $this->resourceId, 'date' => $this->date];
    }

    /**
     * Arrays and other non-scalar values (manipulated requests) are ignored.
     */
    private static function stringOrNull(mixed $value): string|null
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
