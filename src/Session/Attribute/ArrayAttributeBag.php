<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Session\Attribute;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\System;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 *
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
        //protected readonly ContaoFramework $framework,
        protected readonly RequestStack $requestStack,
        protected readonly Security $security,
        string $storageKey = '_sf2_attributes'
    ) {
        parent::__construct();
    }

    /**
     * @param mixed $offset
     * @return bool
     * @throws \Exception
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function &offsetGet($offset): mixed
    {
        return $this->attributes[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return void
     * @throws \Exception
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * @param mixed $offset
     * @return void
     * @throws \Exception
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * @param $name
     * @return bool
     * @throws \Exception
     */
    public function has($name): bool
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return isset($arrSession[$name]);
    }

    /**
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     * @throws \Exception
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return $arrSession[$name] ?? $default;
    }

    /**
     * @param $name
     * @param $value
     * @return void
     * @throws \Exception
     */
    public function set($name, $value): void
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrSession[$name] = $value;

        parent::set($sessKey, $arrSession);
    }

    /**
     * @param array $attributes
     * @return void
     * @throws \Exception
     */
    public function replace(array $attributes): void
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrNew = array_merge($arrSession, $attributes);
        parent::set($sessKey, $arrNew);
    }

    /**
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function remove(string $name): mixed
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        if (isset($arrSession[$name])) {
            unset($arrSession[$name]);
            parent::set($sessKey, $arrSession);
        }

        return $arrSession;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function clear(): mixed
    {
        $sessKey = $this->getSessionBagSubkey();
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
     * @return int
     * @throws \Exception
     */
    public function count(): int
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]) && \is_array($arrSessionAll[$sessKey])) {
            return \count($arrSessionAll[$sessKey]);
        }

        return 0;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getSessionBagSubkey(): string
    {
        /** @var Environment $environmentAdapter */

        $framework = System::getContainer()->get('contao.framework');
        $framework->initialize();
        $environmentAdapter = $framework->getAdapter(Environment::class);

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

        if ($this->security->getUser() instanceof FrontendUser) {
            /** @var FrontendUser $objUser */
            $objUser = $this->security->getUser();

            if ($objUser->id > 0) {
                $userId = $objUser->id;
            }
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($environmentAdapter->get('isAjaxRequest')) {
            $moduleKey = $request->request->get('moduleKey');
        } elseif (!$environmentAdapter->get('isAjaxRequest') && \strlen((string) ModuleKey::getModuleKey()) && \strlen(TokenManager::getToken())) {
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
}
