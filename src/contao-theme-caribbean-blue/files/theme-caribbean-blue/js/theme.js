if (navigator.appVersion.indexOf("MSIE 9.") !== -1) {
    alert('Diese Webseite verwendet Technologien, die von älteren Browsern nicht unterstützt werden. Deswegen kann es sein, dass das Layout nicht optimal dargestellt wird. Sie verwenden Internet Explorer 9. Aktualisieren Sie Ihren Microsoft-Browser mindestens auf Version 11 oder nutzen Sie die aktuellen Versionen von Safari, Firefox, Opera oder Google Chrome. Besten Dank für Ihr Verständnis.')
}
// Load resources
(function ($) {
    $().ready(function () {
        new WOW().init();
    });
})(jQuery);

// Header navigation
(function ($) {
    $().ready(function () {
        $('#header .mod_navigation ul.level_1 > li > a, #header .mod_navigation ul.level_1 > li>a:after, #header .mod_navigation ul.level_1 > li > strong').click(function (e) {
            if (!$(this).closest('li').hasClass('submenu')) {
                return true;
            } else {
                e.stopPropagation();
                e.preventDefault();
            }

            $(this).closest('ul').find('.expanded').removeClass('expanded');
            $(this).closest('li').addClass('expanded');

            $(window).resize(function (e) {
                e.preventDefault();
                dispandNavigation();
            });
            $(window).scroll(function (e) {
                e.preventDefault();
                dispandNavigation();
            });

        });
        // Close Navigation when clicking outside
        $(document).on('click', function (e) {
            var clickedEl = $(e.target);
            var outsideClicker = $('#header .mod_navigation');

            if (!(clickedEl.is(outsideClicker) || outsideClicker.has(clickedEl).length > 0)) {
                //console.log('I clicked outside the target!');
                //e.preventDefault();
                dispandNavigation();
            } else {
                //console.log('all good'); // if you don't have an else just get rid of this
            }
        });

        function dispandNavigation() {
            $('.mod_navigation .expanded').removeClass('expanded');
        }

    });
})(jQuery);


/** Scroll to top button **/
(function ($) {
    $().ready(function () {
        $('body').append('<div class="scroll-to-top"><a href="#"><span class="fa fa-chevron-up"></span></a></div>');

        //Check to see if the window is top if not then display button
        $(window).on('scroll', function () {
            if ($(this).scrollTop() > 100) {
                $('.scroll-to-top').fadeIn();
            } else {
                $('.scroll-to-top').fadeOut();
            }
        });

        //Click event to scroll to top
        $('.scroll-to-top').click(function () {
            $('html, body').animate({scrollTop: 0}, 800);
            return false;
        });
    });
})(jQuery);


/** shorten download links **/

(function ($) {
    $().ready(function () {
        if (window.screen.width < 800) {
            var maxStringLength = 18;

        } else {
            maxStringLength = 40;
        }
        var classes = ['.ce_downloads a', '.ce_download a'];
        $.each(classes, function (index, strClass) {
            $(strClass).each(function (index, el) {
                var strFilename = el.innerHTML;
                var match = strFilename.match(/(.*)\<span(.*)/);
                if (match) {
                    var filename = match[1];
                    if (filename.length > maxStringLength) {
                        var filenameShortened = filename.substring(0, maxStringLength) + ' … ';
                        el.innerHTML = filenameShortened + '<span' + match[2];
                    }
                }
            });
        });
    });
})(jQuery);

