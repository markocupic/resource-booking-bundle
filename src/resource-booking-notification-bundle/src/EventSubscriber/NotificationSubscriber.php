<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Notification Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license LGPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-notification-bundle
 */

namespace Markocupic\ResourceBookingNotificationBundle\EventSubscriber;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\ResourceBookingBundle\Booking\Booking;
use Markocupic\ResourceBookingBundle\Event\PostBookingEvent;
use Markocupic\ResourceBookingBundle\Event\PostCancelingEvent;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceModel;
use Markocupic\ResourceBookingBundle\Model\ResourceBookingResourceTypeModel;
use Markocupic\ResourceBookingBundle\Session\Attribute\ArrayAttributeBag;
use Markocupic\ResourceBookingBundle\User\LoggedInFrontendUser;
use NotificationCenter\Model\Notification;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class NotificationSubscriber.
 */
final class NotificationSubscriber implements EventSubscriberInterface
{
    const PRIORITY = 1000;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var LoggedInFrontendUser
     */
    private $user;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var ArrayAttributeBag
     */
    private $sessionBag;

    /**
     * NotificationSubscriber constructor.
     */
    public function __construct(ContaoFramework $framework, LoggedInFrontendUser $user, SessionInterface $session, string $bagName)
    {
        $this->framework = $framework;
        $this->user = $user;
        $this->session = $session;
        $this->sessionBag = $session->getBag($bagName);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostBookingEvent::NAME   => ['notifyOnPostBooking', self::PRIORITY],
            PostCancelingEvent::NAME => ['notifyOnPostCanceling', self::PRIORITY],
        ];
    }

    public function notifyOnPostBooking(PostBookingEvent $objEvent): bool
    {
        $notificationAdapter = $this->framework->getAdapter(Notification::class);
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        $moduleId = $this->sessionBag->get('moduleModelId');
        $objModule = $moduleModelAdapter->findByPk($moduleId);

        if (null === $objModule) {
            return false;
        }

        $objNotification = $notificationAdapter->findByPk($objModule->rbbOnBookingNotification);

        if (null === $objNotification) {
            return false;
        }

        return $this->notify($objEvent, $objNotification);
    }

    public function notifyOnPostCanceling(PostCancelingEvent $objEvent): bool
    {
        $notificationAdapter = $this->framework->getAdapter(Notification::class);
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        $moduleId = $this->sessionBag->get('moduleModelId');
        $objModule = $moduleModelAdapter->findByPk($moduleId);

        if (null === $objModule) {
            return false;
        }

        $objNotification = $notificationAdapter->findByPk($objModule->rbbOnCancelingNotification);

        if (null === $objNotification) {
            return false;
        }

        return $this->notify($objEvent, $objNotification);
    }

    private function notify(Event $objEvent, Notification $objNotification): bool
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $resourceBookingResourceAdapter = $this->framework->getAdapter(ResourceBookingResourceModel::class);
        $resourceBookingResourceTypeAdapter = $this->framework->getAdapter(ResourceBookingResourceTypeModel::class);

        if (!$this->user->getLoggedInUser() instanceof FrontendUser || !$this->user->getLoggedInUser()->id) {
            return false;
        }

        if (null === ($objBookings = $objEvent->getBookingCollection())) {
            return false;
        }

        if (null === ($objResource = $resourceBookingResourceAdapter->findByPk($this->sessionBag->get('res', 0)))) {
            return false;
        }

        if (null === ($objResourceType = $resourceBookingResourceTypeAdapter->findByPk($this->sessionBag->get('resType', 0)))) {
            return false;
        }

        $objMember = $memberModelAdapter->findByPk($this->user->getLoggedInUser()->id);

        if (null === $objMember) {
            return false;
        }

        if (!$this->sessionBag->has('moduleModelId')) {
            return false;
        }

        $arrTokens = [];

        // Set tokens about booking person
        $arrMember = $objMember->row();

        foreach ($arrMember as $k => $v) {
            if ('password' === $k) {
                continue;
            }
            $arrTokens['booking_person_'.$k] = $this->getCleanedValue($v);
        }

        // Set tokens about booking slot
        $strDetails = '';
        $i = 0;

        $objBookings->reset();

        while ($objBookings->next()) {
            ++$i;

            if (1 === $i) {
                foreach ($objBookings->row() as $k => $v) {

                    $arrTokens['booking_'.$k] = $this->getCleanedValue($v);
                }
            }

            $strDetails .= $objBookings->title."\r\n";
        }
        unset($arrTokens['booking_title']);
        $arrTokens['booking_details'] = $strDetails;
        $arrTokens['booking_details_html'] = nl2br($strDetails);
        $arrTokens['booking_datim'] = date($configAdapter->get('datimFormat'), time());

        // Set tokens about resource
        foreach ($objResource->row() as $k => $v) {
            $arrTokens['booking_resource_'.$k] = $this->getCleanedValue($v);
        }

        // Set tokens about resource type
        foreach ($objResourceType->row() as $k => $v) {
            $arrTokens['booking_resource_type_'.$k] = $this->getCleanedValue($v);
        }

        // Send notification
        $objNotification->send($arrTokens, $this->sessionBag->get('language', 'en'));

        return true;
    }

    private function getCleanedValue($value)
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        if (empty($value)) {
            return '';
        }

        $value = serialize($value);

        if ($this->isBinary($value)) {
            if ($arr = $stringUtilAdapter->deserialize($value)) {
                if (!empty($arr) && \is_array($arr)) {
                    return serialize(
                        array_map(
                            function ($val) {
                                return $stringUtilAdapter->binToUuid($val);
                            },
                            $arr
                        )
                    );

                }
            } else {
                return $stringUtilAdapter->binToUuid($value);
            }
        }

        return $value;
    }

    private function isBinary($str)
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }
}
