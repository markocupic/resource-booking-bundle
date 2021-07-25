<?php

declare(strict_types=1);

/*
 * This file is part of Contao Sidebar Navigation.
 * 
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-sidebar-navigation
 */

namespace Markocupic\ContaoSidebarNavigation;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MarkocupicContaoSidebarNavigation
 */
class MarkocupicContaoSidebarNavigation extends Bundle
{
	/**
	 * {@inheritdoc}
	 */
	public function build(ContainerBuilder $container): void
	{
		parent::build($container);
		
	}
}
