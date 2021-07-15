<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Response;

/**
 * Class AjaxResponse.
 */
class AjaxResponse
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    public const MESSAGE_CONFIRMATION = 'confirmation';

    public const MESSAGE_INFO = 'info';

    public const MESSAGE_ERROR = 'error';

    /**
     * @var array
     */
    private $arrData;

    /**
     * JsonResponse constructor.
     */
    public function __construct()
    {
        $this->arrData = [
            'status' => null,
            'data' => [
                'messages' => [
                    static::MESSAGE_ERROR => null,
                    static::MESSAGE_CONFIRMATION => null,
                    static::MESSAGE_INFO => null,
                ],
            ],
        ];
    }

    public function getAll(): array
    {
        return $this->arrData;
    }

    /**
     * @throws \Exception
     */
    public function setStatus(string $strStatus): void
    {
        if ($strStatus !== static::STATUS_ERROR && $strStatus !== static::STATUS_SUCCESS) {
            throw new \Exception(sprintf('Status must be either %s or %s and can not be "%s"', static::STATUS_ERROR, static::STATUS_SUCCESS, $strStatus));
        }
        $this->arrData['status'] = $strStatus;
    }

    public function getStatus(): ?string
    {
        return $this->arrData['status'];
    }

    public function setConfirmationMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::MESSAGE_CONFIRMATION] = $strMessage;
    }

    public function getConfirmationMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::MESSAGE_CONFIRMATION];
    }

    public function setInfoMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::MESSAGE_INFO] = $strMessage;
    }

    public function getInfoMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::MESSAGE_INFO];
    }

    public function setErrorMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::MESSAGE_ERROR] = $strMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::MESSAGE_ERROR];
    }

    /**
     * @param $value
     */
    public function setData(string $key, $value): void
    {
        $this->arrData['data'][$key] = $value;
    }

    public function getData(string $key): ?array
    {
        if (isset($this->arrData['data'][$key])) {
            return $this->arrData['data'][$key];
        }

        return null;
    }

    public function setDataFromArray(array $aValues): void
    {
        $aBefore = $this->arrData['data'];
        $this->arrData['data'] = array_merge($aBefore, $aValues);
    }
}
