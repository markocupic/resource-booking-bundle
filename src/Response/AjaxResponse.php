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

use Markocupic\ResourceBookingBundle\Api\Output\ApiResultInterface;

/**
 * Response of an Ajax request of the week calendar. The Ajax controllers set the status,
 * the messages and the result; listeners of the AjaxResponseEvent may add or change data.
 *
 * JSON (toArray()):
 *
 *     {
 *         "status": "success"|"warning"|"error"|null,
 *         "messages": {"warning": …, "error": …, "confirm": …, "info": …},
 *         "fieldErrors": {"<field>": "<message>"},
 *         "data": {…},
 *         "action": "<action>"
 *     }
 */
final class AjaxResponse implements \JsonSerializable
{
    private AjaxStatus|null $status = null;

    /**
     * @var array<string, string>
     */
    private array $messages = [];

    /**
     * Messages per form field (field name => message), see FieldValidationException.
     *
     * @var array<string, string>
     */
    private array $fieldErrors = [];

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private string $action)
    {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getStatus(): AjaxStatus|null
    {
        return $this->status;
    }

    public function setStatus(AjaxStatus $status): void
    {
        $this->status = $status;
    }

    public function getMessage(MessageType $type): string|null
    {
        return $this->messages[$type->value] ?? null;
    }

    /**
     * An empty message counts as no message.
     */
    public function hasMessage(MessageType $type): bool
    {
        return '' !== ($this->messages[$type->value] ?? '');
    }

    public function setMessage(MessageType $type, string $message): void
    {
        $this->messages[$type->value] = $message;
    }

    public function removeMessage(MessageType $type): void
    {
        unset($this->messages[$type->value]);
    }

    public function getConfirmationMessage(): string|null
    {
        return $this->getMessage(MessageType::Confirm);
    }

    public function hasConfirmationMessage(): bool
    {
        return $this->hasMessage(MessageType::Confirm);
    }

    public function setConfirmationMessage(string $message): void
    {
        $this->setMessage(MessageType::Confirm, $message);
    }

    public function getErrorMessage(): string|null
    {
        return $this->getMessage(MessageType::Error);
    }

    public function hasErrorMessage(): bool
    {
        return $this->hasMessage(MessageType::Error);
    }

    public function setErrorMessage(string $message): void
    {
        $this->setMessage(MessageType::Error, $message);
    }

    public function getInfoMessage(): string|null
    {
        return $this->getMessage(MessageType::Info);
    }

    public function hasInfoMessage(): bool
    {
        return $this->hasMessage(MessageType::Info);
    }

    public function setInfoMessage(string $message): void
    {
        $this->setMessage(MessageType::Info, $message);
    }

    public function getWarningMessage(): string|null
    {
        return $this->getMessage(MessageType::Warning);
    }

    public function hasWarningMessage(): bool
    {
        return $this->hasMessage(MessageType::Warning);
    }

    public function setWarningMessage(string $message): void
    {
        $this->setMessage(MessageType::Warning, $message);
    }

    /**
     * Message for a form field of the booking form. The front end links it to the
     * field with the same name (aria-invalid, aria-describedby).
     */
    public function setFieldError(string $field, string $message): void
    {
        $this->fieldErrors[$field] = $message;
    }

    /**
     * @return array<string, string>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function hasFieldErrors(): bool
    {
        return [] !== $this->fieldErrors;
    }

    /**
     * Sets the result of the action (typed data of the JSON response). Listeners of the
     * AjaxResponseEvent can add further data with setData() or mergeData().
     */
    public function setResult(ApiResultInterface $result): void
    {
        $this->mergeData($result->toArray());
    }

    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function getData(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * All data of the response (JSON key "data").
     *
     * @return array<string, mixed>
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * Adds the values to the data; existing keys are overwritten.
     *
     * @param array<string, mixed> $values
     */
    public function mergeData(array $values): void
    {
        $this->data = [...$this->data, ...$values];
    }

    /**
     * The JSON of the response. An error message replaces the info and confirmation
     * messages (e.g. "Booking successful" set by a listener before the error occurred).
     *
     * @return array{status: string|null, messages: array<string, string|null>, fieldErrors: array<string, string>, data: array<string, mixed>, action: string}
     */
    public function toArray(): array
    {
        $hasError = $this->hasErrorMessage();
        $messages = [];

        foreach (MessageType::cases() as $type) {
            $messages[$type->value] = $hasError && \in_array($type, [MessageType::Info, MessageType::Confirm], true) ? null : $this->getMessage($type);
        }

        return [
            'status' => $this->status?->value,
            'messages' => $messages,
            'fieldErrors' => $this->fieldErrors,
            'data' => $this->data,
            'action' => $this->action,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
