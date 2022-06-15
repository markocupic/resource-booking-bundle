<?php

declare(strict_types=1);

/*
 * This file is part of Resource Booking Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/resource-booking-bundle
 */

namespace Markocupic\ResourceBookingBundle;

use Markocupic\ResourceBookingBundle\DependencyInjection\Compiler\AddAjaxControllerPass;
use Markocupic\ResourceBookingBundle\DependencyInjection\Compiler\AddSessionBagsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MarkocupicResourceBookingBundle.
 */
class MarkocupicResourceBookingBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AddSessionBagsPass());
        $container->addCompilerPass(new AddAjaxControllerPass());
    }
}
