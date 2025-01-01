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

namespace Markocupic\ResourceBookingBundle\Session\Attribute;

use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The module key is necessary to run multiple rbb applications on the same page
 * and is sent as a post parameter on every xhr request.
 *
 * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][$sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token]
 *
 * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
 * The module index is 0, if the current module is the first rbb module on the current page
 * The module index is 1, if the current module is the first rbb module on the current page, etc.
 *
 * Do only run once ModuleIndex::generateModuleIndex() per module instance;
 */
class ArrayAttributeBag extends AttributeBag implements \ArrayAccess
{
    public function __construct(
        protected readonly RequestStack $requestStack,
        protected readonly TokenStorageInterface $tokenStorage,
        string $storageKey = '_sf2_attributes',
    ) {
        parent::__construct($storageKey);
    }

    /**
     * @throws \Exception
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param $name
     *
     * @throws \Exception
     */
    public function has($name): bool
    {
        $sessKey = $this->getSessionBagKey();
        $arrSession = parent::get($sessKey, []);

        return isset($arrSession[$name]);
    }

    /**
     * @throws \Exception
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $sessKey = $this->getSessionBagKey();
        $arrSession = parent::get($sessKey, []);

        return $arrSession[$name] ?? $default;
    }

    /**
     * @param $offset
     */
    public function &offsetGet($offset): mixed
    {
        return $this->attributes[$offset];
    }

    /**
     * @throws \Exception
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * @param $name
     * @param $value
     *
     * @throws \Exception
     */
    public function set($name, $value): void
    {
        $sessKey = $this->getSessionBagKey();
        $arrSession = parent::get($sessKey, []);
        $arrSession[$name] = $value;

        parent::set($sessKey, $arrSession);
    }

    /**
     * @throws \Exception
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * @throws \Exception
     */
    public function remove(string $name): mixed
    {
        $sessKey = $this->getSessionBagKey();
        $arrSession = parent::get($sessKey, []);

        if (isset($arrSession[$name])) {
            unset($arrSession[$name]);
            parent::set($sessKey, $arrSession);
        }

        return $arrSession;
    }

    /**
     * @throws \Exception
     */
    public function replace(array $attributes): void
    {
        $sessKey = $this->getSessionBagKey();
        $arrSession = parent::get($sessKey, []);
        $arrNew = array_merge($arrSession, $attributes);
        parent::set($sessKey, $arrNew);
    }

    /**
     * @throws \Exception
     */
    public function clear(): null
    {
        $sessKey = $this->getSessionBagKey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey])) {
            unset($arrSessionAll[$sessKey]);

            foreach ($arrSessionAll as $k => $v) {
                parent::set($k, $v);
            }
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public function count(): int
    {
        $sessKey = $this->getSessionBagKey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]) && \is_array($arrSessionAll[$sessKey])) {
            return \count($arrSessionAll[$sessKey]);
        }

        return 0;
    }

    /**
     * @throws \Exception
     */
    private function getSessionBagKey(): string
    {
        /**
         * The module key is necessary to run multiple rbb applications on the same page
         * and is sent as a post parameter on every xhr request.
         *
         * The session data of each rbb instance is stored under $_SESSION[_resource_booking_bundle_attributes][$sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token]
         *
         * The module key (#moduleId_#moduleIndex f.ex. 33_0) contains the module id and the module index
         * The module index is 0, if the current module is the first rbb module on the current page
         * The module index is 1, if the current module is the first rbb module on the current page, etc.
         *
         * Do only run once ModuleIndex::generateModuleIndex() per module instance;
         */
        $sessionId = '';
        $userId = '';

        $session = $this->requestStack->getCurrentRequest()->getSession();

        if ($session->isStarted()) {
            $sessionId = $session->getId();
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!empty($user) && $user instanceof FrontendUser) {
            $userId = $user->id;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($this->isAjaxRequest()) {
            $moduleKey = $request->request->get('moduleKey');
        } elseif (!empty((string) ModuleKey::getModuleKey()) && !empty(TokenManager::getToken())) {
            $moduleKey = ModuleKey::getModuleKey();
        } else {
            return '';
        }

        // Get the token from url
        if (!$request->query->has('token_'.$moduleKey)) {
            return '';
        }

        $token = $request->query->get('token_'.$moduleKey);

        return sha1($sessionId.'_'.$userId.'_'.$moduleKey.'_'.$token);
    }

    private function isAjaxRequest(): bool
    {
        return $this->requestStack->getCurrentRequest()->isXmlHttpRequest();
    }
}
