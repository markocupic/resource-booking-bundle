/*
 * This file is part of Contao Sidebar Navigation.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/contao-sidebar-navigatio
 */
jQuery(document).ready(function () {
    // Insert dropdown toggle button
    jQuery('<button class="toggle-submenu" role="button"></button>')
    .insertAfter(".sidebar-navigation li.submenu > a, .sidebar-navigation li.submenu > strong");

    // Add aria-expanded attribute and expanded class
    jQuery('.sidebar-navigation li.submenu:not(.trail)')
    .attr('aria-expanded', 'false');

    jQuery('.sidebar-navigation li.submenu.trail, .sidebar-navigation li.submenu.active')
    .addClass('expanded')
    .attr('aria-expanded', 'true');

    // Handle click events on the dropdown toggle button
    setTimeout(function () {
        jQuery('.sidebar-navigation .toggle-submenu').click(function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Close menu
            jQuery(this).closest('li:not(.expanded)')
            .find('li.expanded')
            .removeClass('expanded')
            .attr('aria-expanded', 'false')
            .children('ul')
            .slideUp();

            // Close opened siblings
            jQuery(this).closest('li')
            .siblings('li.expanded')
            .removeClass('expanded')
            .attr('aria-expanded', 'false')
            .children('ul')
            .slideUp();

            // Open/close item
            if (jQuery(this).closest('li').hasClass('expanded')) {
                jQuery(this).closest('li')
                .removeClass('expanded')
                .attr('aria-expanded', 'false')
                .children('ul')
                .slideUp();
            } else {
                jQuery(this).closest('li')
                .addClass('expanded')
                .attr('aria-expanded', 'true')
                .children('ul')
                .slideDown();
            }
        });
    }, 20);
});
