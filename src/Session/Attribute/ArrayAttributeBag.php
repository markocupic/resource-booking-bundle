<?php

declare(strict_types=1);

/**
 * Resource Booking Module for Contao CMS
 * Copyright (c) 2008-2019 Marko Cupic
 * @package resource-booking-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2019
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle\Session\Attribute;

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

/**
 * Provides an array access adapter for a session attribute bag.
 */
class ArrayAttributeBag extends AttributeBag implements \ArrayAccess
{
    /**
     * {@inheritdoc}
     */
    public function offsetExists($key): bool
    {
        return $this->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function &offsetGet($key)
    {
        return $this->attributes[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($key, $value): void
    {
        $this->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($key): void
    {
        $this->remove($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public function has($key)
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSession = parent::get($sessKey, []);
        return isset($arrSession[$key]) ? true : false;
    }

    /**
     * @param $key
     * @param null $mixed
     * @return mixed|null
     */
    public function get($key, $mixed = null)
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSession = parent::get($sessKey, []);
        return isset($arrSession[$key]) ? $arrSession[$key] : null;
    }

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSession = parent::get($sessKey, []);
        $arrSession[$key] = $value;

        return parent::set($sessKey, $arrSession);
    }

    /**
     * @param array $arrAttributes
     */
    public function replace(array $arrAttributes)
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSession = parent::get($sessKey, []);
        $arrNew = array_merge($arrSession, $arrAttributes);
        parent::set($sessKey, $arrNew);
    }

    /**
     * @param $key
     * @return mixed|null|void
     */
    public function remove($key)
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSession = parent::get($sessKey, []);
        if (isset($arrSession[$key]))
        {
            unset($arrSession[$key]);
            parent::set($sessKey, $arrSession);
        }
    }

    /**
     * @return array|mixed|void
     */
    public function clear()
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]))
        {
            unset($arrSessionAll[$sessKey]);
            foreach ($arrSessionAll as $k => $v)
            {
                parent::set($k, $v);
            }
        }
    }

    /**
     * @return int
     */
    public function count()
    {
        $sessKey = $_GET['sessionId'] != '' ? sprintf('___%s___', $_GET['sessionId']) : '';
        $arrSessionAll = parent::all();

        if (isset($arrSessionAll[$sessKey]) && is_array($arrSessionAll))
        {
           return count($arrSessionAll[$sessKey]);
        }
        return 0;
    }

}
