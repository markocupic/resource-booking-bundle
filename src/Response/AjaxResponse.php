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

namespace Markocupic\ResourceBookingBundle\Response;

class AjaxResponse
{
    public const MESSAGE_CONFIRM = 'confirm';
    public const MESSAGE_ERROR = 'error';
    public const MESSAGE_INFO = 'info';
    public const MESSAGE_WARNING = 'warning';
    public const STATUS_ERROR = 'error';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';

    private array $arrData;

    public function __construct(string $action)
    {
        $this->arrData = [
            'status'   => null,
            'messages' => [
                static::MESSAGE_WARNING => null,
                static::MESSAGE_ERROR   => null,
                static::MESSAGE_CONFIRM => null,
                static::MESSAGE_INFO    => null,
            ],
            'data'     => [],
        ];

        $this->setAction($action);
    }

    public function setAction(string $strAction): void
    {
        $this->arrData['action'] = $strAction;
    }

    public function prepareBeforeSend(bool $delInfAndConfMsgIfThereAreErrMsg = false): self
    {
        if ($delInfAndConfMsgIfThereAreErrMsg && $this->hasErrorMessage()) {
            $this->deleteInfoMessage();
            $this->deleteConfirmationMessage();
        }

        return $this;
    }

    public function hasErrorMessage(): bool
    {
        return !empty($this->arrData['messages'][static::MESSAGE_ERROR]);
    }

    public function deleteInfoMessage(): void
    {
        $this->arrData['messages'][static::MESSAGE_INFO] = null;
    }

    public function deleteConfirmationMessage(): void
    {
        $this->arrData['messages'][static::MESSAGE_CONFIRM] = null;
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
        if ($strStatus !== static::STATUS_ERROR && $strStatus !== static::STATUS_SUCCESS && $strStatus !== static::STATUS_WARNING) {
            throw new \Exception(sprintf('Status must be either %s, %s or %s and can not be "%s"', static::STATUS_ERROR, static::STATUS_WARNING, static::STATUS_SUCCESS, $strStatus));
        }

        $this->arrData['status'] = $strStatus;
    }

    public function getStatus(): string|null
    {
        return $this->arrData['status'];
    }

    public function getAction(): string|null
    {
        return $this->arrData['action'] ?? null;
    }

    public function getConfirmationMessage(): string|null
    {
        return $this->arrData['messages'][static::MESSAGE_CONFIRM];
    }

    public function hasConfirmationMessage(): bool
    {
        return !empty($this->arrData['messages'][static::MESSAGE_CONFIRM]);
    }

    public function setConfirmationMessage(string $strMessage): void
    {
        $this->arrData['messages'][static::MESSAGE_CONFIRM] = $strMessage;
    }

    public function getInfoMessage(): string|null
    {
        return $this->arrData['messages'][static::MESSAGE_INFO];
    }

    public function hasInfoMessage(): bool
    {
        return !empty($this->arrData['messages'][static::MESSAGE_INFO]);
    }

    public function setInfoMessage(string $strMessage): void
    {
        $this->arrData['messages'][static::MESSAGE_INFO] = $strMessage;
    }

    public function deleteErrorMessage(): void
    {
        $this->arrData['messages'][static::MESSAGE_ERROR] = null;
    }

    public function getErrorMessage(): string|null
    {
        return $this->arrData['messages'][static::MESSAGE_ERROR];
    }

    public function setErrorMessage(string $strMessage): void
    {
        $this->arrData['messages'][static::MESSAGE_ERROR] = $strMessage;
    }

    public function deleteWarningMessage(): void
    {
        $this->arrData['messages'][static::MESSAGE_WARNING] = null;
    }

    public function getWarningMessage(): string|null
    {
        return $this->arrData['messages'][static::MESSAGE_WARNING];
    }

    public function hasWarningMessage(): bool
    {
        return !empty($this->arrData['messages'][static::MESSAGE_WARNING]);
    }

    public function setWarningMessage(string $strMessage): void
    {
        $this->arrData['messages'][static::MESSAGE_WARNING] = $strMessage;
    }

    /**
     * @param $value
     */
    public function setData(string $key, $value): void
    {
        $this->arrData['data'][$key] = $value;
    }

    public function getData(string $key): array|null
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
