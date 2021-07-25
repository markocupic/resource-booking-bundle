<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

declare(strict_types=1);

namespace Markocupic\Office365Bundle\Message;

/**
 * Class SessionMessage
 * @package Markocupic\Office365Bundle\Message
 */
class SessionMessage
{

    /**
     * SessionMessage constructor.
     */
    public function __construct()
    {
        if (!isset($_SESSION['Office365Bundle']['messages']['info']))
        {
            $_SESSION['Office365Bundle']['messages']['info'] = [];
            $_SESSION['Office365Bundle']['messages']['error'] = [];
        }
    }

    /**
     * @param string $strMessage
     */
    public function addInfoMessage(string $strMessage): void
    {
        $_SESSION['Office365Bundle']['messages']['info'][] = $strMessage;
    }

    /**
     * @param string $strMessage
     */
    public function addErrorMessage(string $strMessage): void
    {
        $_SESSION['Office365Bundle']['messages']['error'][] = $strMessage;
    }

    /**
     * @return bool
     */
    public function hasInfoMessages(): bool
    {
        return !empty($_SESSION['Office365Bundle']['messages']['info']);
    }

    /**
     * @return bool
     */
    public function hasErrorMessages(): bool
    {
        return !empty($_SESSION['Office365Bundle']['messages']['error']);
    }

    /**
     * Delete messages and return them as array
     * @return array
     */
    public function getInfoMessages(): array
    {
        $arrMsg = $_SESSION['Office365Bundle']['messages']['info'];
        $_SESSION['Office365Bundle']['messages']['info'] = [];
        return $arrMsg;
    }

    /**
     * Delete messages and return them as array
     * @return array
     */
    public function getErrorMessages(): array
    {
        $arrMsg = $_SESSION['Office365Bundle']['messages']['error'];
        $_SESSION['Office365Bundle']['messages']['error'] = [];
        return $arrMsg;
    }

    /**
     * Delete info messages
     */
    public function deleteInfoMessages(): void
    {
        $_SESSION['Office365Bundle']['messages']['info'] = [];
    }

    /**
     * Delete error messages
     */
    public function deleteErrorMessages(): void
    {
        $_SESSION['Office365Bundle']['messages']['error'] = [];
    }

}
