<?php

declare(strict_types=1);

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

namespace Markocupic\BackendPasswordRecoveryBundle\Subscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LoadAssets implements EventSubscriberInterface
{
    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    public function __construct(ScopeMatcher $scopeMatcher)
    {
        $this->scopeMatcher = $scopeMatcher;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'loadAssets'];
    }

    public function loadAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($request && $this->scopeMatcher->isBackendRequest($request)) {
            $GLOBALS['TL_CSS'][] = 'bundles/markocupicbackendpasswordrecovery/stylesheet.css';
        }
    }
}
