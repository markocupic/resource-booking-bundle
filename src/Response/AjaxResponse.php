<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2020 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Response;

/**
 * Class AjaxResponse.
 */
class AjaxResponse
{
    /**
     * @var string
     */
    public const STATUS_SUCCESS = 'success';

    /**
     * @var string
     */
    public const STATUS_INFO = 'info';

    /**
     * @var string
     */
    public const STATUS_ERROR = 'error';

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
                    static::STATUS_ERROR   => null,
                    static::STATUS_SUCCESS => null,
                    static::STATUS_INFO    => null,
                ]
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
        if ($strStatus !== static::STATUS_SUCCESS && $strStatus !== static::STATUS_INFO && $strStatus !== static::STATUS_ERROR) {
            throw new \Exception(sprintf('Status must be either %s, %s or %s and can not be "%s"', static::STATUS_SUCCESS, static::STATUS_INFO, static::STATUS_ERROR, $strStatus));
        }
        $this->arrData['status'] = $strStatus;
    }

    public function getStatus(): ?string
    {
        return $this->arrData['status'];
    }

    public function setSuccessMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::STATUS_SUCCESS] = $strMessage;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::STATUS_SUCCESS];
    }

    public function setInfoMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::STATUS_INFO] = $strMessage;
    }

    public function getInfoMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::STATUS_INFO];
    }

    public function setErrorMessage(string $strMessage): void
    {
        $this->arrData['data']['messages'][static::STATUS_ERROR] = $strMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->arrData['data']['messages'][static::STATUS_ERROR];
    }

    /**
     * @param $value
     */
    public function setData(string $key, $value): void
    {
        $this->arrData['data'][$key] = $value;
    }

    /**
     * @return mixed|null
     */
    public function getData(string $key)
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
