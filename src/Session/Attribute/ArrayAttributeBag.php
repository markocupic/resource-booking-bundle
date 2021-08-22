<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Session\Attribute;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendUser;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\ModuleKey;
use Markocupic\ResourceBookingBundle\AppInitialization\Helper\TokenManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class ArrayAttributeBag.
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
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private SessionInterface $session;
    private Security $security;

    /**
     * ArrayAttributeBag constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, SessionInterface $session, Security $security, string $storageKey = '_sf2_attributes')
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->session = $session;
        $this->security = $security;

        parent::__construct($storageKey);
    }

    /**
     * @param mixed $key
     *
     * @throws \Exception
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * @param mixed $key
     *
     * @return mixed
     */
    public function &offsetGet($key)
    {
        return $this->attributes[$key];
    }

    /**
     * @param mixed $key
     * @param mixed $value
     *
     * @throws \Exception
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * @param mixed $key
     *
     * @throws \Exception
     */
    public function offsetUnset($key): void
    {
        $this->remove($key);
    }

    /**
     * @param $key
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function has($key)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return isset($arrSession[$key]) ? true : false;
    }

    /**
     * @param $key
     * @param null $mixed
     *
     * @throws \Exception
     *
     * @return mixed|null
     */
    public function get($key, $mixed = null)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        return $arrSession[$key] ?? null;
    }

    /**
     * @param $key
     * @param $value
     *
     * @throws \Exception
     */
    public function set($key, $value)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrSession[$key] = $value;

        return parent::set($sessKey, $arrSession);
    }

    /**
     * @throws \Exception
     */
    public function replace(array $arrAttributes): void
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);
        $arrNew = array_merge($arrSession, $arrAttributes);
        parent::set($sessKey, $arrNew);
    }

    /**
     * @param $key
     *
     * @throws \Exception
     *
     * @return mixed|void|null
     */
    public function remove($key)
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSession = parent::get($sessKey, []);

        if (isset($arrSession[$key])) {
            unset($arrSession[$key]);
            parent::set($sessKey, $arrSession);
        }
    }

    /**
     * @throws \Exception
     *
     * @return array|mixed|void
     */
    public function clear()
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey])) {
            unset($arrSessionAll[$sessKey]);

            foreach ($arrSessionAll as $k => $v) {
                parent::set($k, $v);
            }
        }
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function count()
    {
        $sessKey = $this->getSessionBagSubkey();
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]) && \is_array($arrSessionAll)) {
            return \count($arrSessionAll[$sessKey]);
        }

        return 0;
    }

    private function getSessionBagSubkey(): string
    {
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

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

        if ($this->session->isStarted()) {
            $sessionId = $this->session->getId();
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
        } elseif (!$environmentAdapter->get('isAjaxRequest') && \strlen((string) ModuleKey::getModuleKey()) && \strlen((string) TokenManager::getToken())) {
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
