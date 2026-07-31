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

namespace Markocupic\ResourceBookingBundle\AjaxController;

use Markocupic\ResourceBookingBundle\AjaxController\Traits\RefreshDataTrait;
use Markocupic\ResourceBookingBundle\Response\AjaxResponse;
use Symfony\Component\HttpFoundation\Request;

final class RefreshDataController extends AbstractController implements ControllerInterface
{
    use RefreshDataTrait;

    public const REQUEST_NAME = 'refreshDataRequest';

    /**
     * @throws \Exception
     */
    public function generateResponse(Request $request, AjaxResponse $ajaxResponse): AjaxResponse
    {
        $ajaxResponse->setStatus(AjaxResponse::STATUS_SUCCESS);
        $ajaxResponse->setDataFromArray($this->getRefreshedData($ajaxResponse));

        return $ajaxResponse;
    }
}
