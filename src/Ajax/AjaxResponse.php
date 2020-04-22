<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Ajax;

/**
 * Class AjaxResponse
 * @package Markocupic\ResourceBookingBundle\Ajax
 */
class AjaxResponse
{

    /** @var string */
    public const STATUS_SUCCESS = 'success';

    /** @var string */
    public const STATUS_ERROR = 'error';

    /** @var array */
    private $arrData;

    /**
     * JsonResponse constructor.
     */
    public function __construct()
    {
        $this->arrData = [
            'status'           => null,
            'message'          => [
                'error'   => null,
                'success' => null,
                'info'    => null,
            ],
            'data'             => [],
        ];
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->arrData;
    }

    /**
     * @param string $strStatus
     * @throws \Exception
     */
    public function setStatus(string $strStatus): void
    {
        if ($strStatus !== static::STATUS_SUCCESS && $strStatus !== static::STATUS_ERROR)
        {
            throw new \Exception(
                sprintf(
                    'Status must be either %s or %s and can not be "%s"',
                    static::STATUS_SUCCESS,
                    static::STATUS_ERROR, $strStatus
                )
            );
        };
        $this->arrData['status'] = $strStatus;
    }

    /**
     * @return null|string
     */
    public function getStatus(): ?string
    {
        return $this->arrData['status'];
    }

    /**
     * @param string $strMessage
     */
    public function setSuccessMessage(string $strMessage): void
    {
        $this->arrData['message']['success'] = $strMessage;
    }

    /**
     * @return null|string
     */
    public function getSuccessMessage(): ?string
    {
        return $this->arrData['message']['success'];
    }

    /**
     * @param string $strMessage
     */
    public function setInfoMessage(string $strMessage): void
    {
        $this->arrData['message']['info'] = $strMessage;
    }

    /**
     * @return null|string
     */
    public function getInfoMessage(): ?string
    {
        return $this->arrData['message']['info'];
    }

    /**
     * @param string $strMessage
     */
    public function setErrorMessage(string $strMessage): void
    {
        $this->arrData['message']['error'] = $strMessage;
    }

    /**
     * @return null|string
     */
    public function getErrorMessage(): ?string
    {
        return $this->arrData['message']['error'];
    }

    /**
     * @param string $key
     * @param $value
     */
    public function setData(string $key, $value): void
    {
        $this->arrData['data'][$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function getData(string $key)
    {
        if (isset($this->arrData['data'][$key]))
        {
            return $this->arrData['data'][$key];
        }
        return null;
    }

    /**
     * @param array $aValues
     */
    public function setDataFromArray(array $aValues): void
    {
        $aBefore = $this->arrData['data'];
        $this->arrData['data'] = array_merge($aBefore, $aValues);
    }

}
