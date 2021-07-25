<?php
if (TL_MODE == 'FE')
{
    $GLOBALS['TL_JAVASCRIPT'][] = 'files/theme-caribbean-blue/js/theme.js|static';
    $GLOBALS['TL_JAVASCRIPT'][] = 'assets/contao-component-bootstrap/bootstrap/dist/js/bootstrap.bundle.min.js|static';
    //$GLOBALS['TL_JAVASCRIPT'][] = 'https://use.fontawesome.com/926b4fc2c0.js';
    $GLOBALS['TL_JAVASCRIPT'][] = 'https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js';
    $GLOBALS['TL_CSS'][] = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.5.2/animate.min.css';

    $GLOBALS['TL_CSS'][] = '/files/fontawesome-pro-5.5.0-web/css/all.css|static';
    $GLOBALS['TL_HEAD'][] = '<script defer src="/files/fontawesome-pro-5.5.0-web/js/all.js"></script>';
}


