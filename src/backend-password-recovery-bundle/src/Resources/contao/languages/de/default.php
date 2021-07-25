<?php

/*
 * This file is part of Backend Password Recovery Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/backend-password-recovery-bundle
 */

/**
 * Errors
 */
$GLOBALS['TL_LANG']['ERR']['pwRecoveryFailed'] = 'In der Benutzer-Datenbank wurde kein Benutzer mit diesem Benutzernamen oder dieser E-Mail-Adresse gefunden.';

/**
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['recoverPassword'] = 'Passwort wiederherstellen';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline'] = 'Passwort wiederherstellen';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'] = 'E-Mail oder Benutzernamen';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailExplain'] = 'Bitte geben Sie Ihre E-Mail-Adresse oder Ihren Benutzernamen ein, um eine E-Mail-Nachricht mit dem Wiederherstellungslink zu erhalten.';
$GLOBALS['TL_LANG']['MSC']['forgotPassword'] = 'Passwort vergessen';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryLinkSuccessfullySent'] = 'Sie erhalten nun in Kürze eine E-Mail mit Hinweisen, wie Sie Ihr Passwort wiederherstellen können. Prüfen Sie auch Ihr Spamverzeichnis, falls sich die Nachricht nicht in Ihrem Posteingang befinden sollte.';
// Email subject
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailSubject'] = 'Ihre Passwort-Anforderung für #host#';
// Email text
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailText'] = '
Hallo #name#

Sie haben ein neues Passwort für #host# angefordert.

Bitte öffnen Sie untenstehenden Link um Ihr neues Passwort einzurichten.

#link#

Falls Sie diese E-Mail nicht angefordert haben, kontaktieren Sie bitte den Administrator der Webseite.



---------------------------------

Dies ist eine automatisch generierte Nachricht. Bitte antworten Sie nicht darauf.
';

