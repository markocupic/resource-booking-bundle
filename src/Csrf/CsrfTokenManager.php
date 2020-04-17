<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2020 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2020
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Csrf;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * Class CsrfTokenManager
 * @package Markocupic\ResourceBookingBundle\Csrf
 */
class CsrfTokenManager
{

    /** @var RequestStack */
    private $requestStack;

    /** @var \Symfony\Component\Security\Csrf\CsrfTokenManager */
    private $contaoCsrfTokenManager;

    /** @var string */
    private $cookiePrefix;

    /** @var string */
    private $csrfTokenName;

    /**
     * CsrfTokenManager constructor.
     * @param RequestStack $requestStack
     * @param \Symfony\Component\Security\Csrf\CsrfTokenManager $contaoCsrfTokenManager
     * @param string $cookiePrefix
     * @param string $csrfTokenName
     */
    public function __construct(RequestStack $requestStack, \Symfony\Component\Security\Csrf\CsrfTokenManager $contaoCsrfTokenManager, string $cookiePrefix, string $csrfTokenName)
    {
        $this->requestStack = $requestStack;
        $this->contaoCsrfTokenManager = $contaoCsrfTokenManager;
        $this->cookiePrefix = $cookiePrefix;
        $this->csrfTokenName = $csrfTokenName;
    }

    /**
     * @return null|string
     */
    public function getCsrfTokenName(): ?string
    {
        foreach ($this->getTokensFromCookies() as $key => $value)
        {
            if ($this->isCsrfCookie($key, $value))
            {
                return $key;
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    public function hasValidCsrfToken(): bool
    {
        foreach ($this->getTokensFromCookies() as $key => $value)
        {
            if ($this->isCsrfCookie($key, $value) && $this->csrfTokenIsValid($value))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @return null|string
     */
    public function getValidCsrfToken(): ?string
    {
        foreach ($this->getTokensFromCookies() as $key => $value)
        {
            if ($this->isCsrfCookie($key, $value) && $this->csrfTokenIsValid($value))
            {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private function getTokensFromCookies(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $cookies = $request->cookies->all();
        $tokens = [];

        foreach ($cookies as $key => $value)
        {
            $tokens[$key] = $value;
        }

        return $tokens;
    }

    /**
     * @param $key
     * @param string $value
     * @return bool
     */
    private function isCsrfCookie($key, string $value): bool
    {
        if (!\is_string($key))
        {
            return false;
        }

        return 0 === strpos($key, $this->cookiePrefix) && preg_match('/^[a-z0-9_-]+$/i', $value);
    }

    /**
     * @param string $strToken
     * @return bool
     */
    private function csrfTokenIsValid($strToken = '')
    {
        return $this->contaoCsrfTokenManager->isTokenValid(new CsrfToken($this->csrfTokenName, $strToken));
    }
}
