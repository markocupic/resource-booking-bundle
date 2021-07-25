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
$GLOBALS['TL_LANG']['ERR']['pwRecoveryFailed'] = 'No user with this username or this e-mail address was found in the user database.';

/**
 * Miscellaneous
 */
$GLOBALS['TL_LANG']['MSC']['recoverPassword'] = 'Go to password recovery';
$GLOBALS['TL_LANG']['MSC']['pwRecoveryHeadline'] = 'Password recovery';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailPlaceholder'] = 'Email or your username';
$GLOBALS['TL_LANG']['MSC']['usernameOrEmailExplain'] = 'Please enter your email address or username to receive an email message with the password recovery link.';
$GLOBALS['TL_LANG']['MSC']['forgotPassword'] = "forgot password";
$GLOBALS['TL_LANG']['MSC']['pwRecoveryLinkSuccessfullySent'] = 'You will shortly receive an email with instructions on how to restore your password. Also check your spam directory if the message is not in your inbox.';
// Email subject
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailSubject'] = 'Your password request on #host#';
// Email text
$GLOBALS['TL_LANG']['MSC']['pwRecoveryEmailText'] = '
Hi #name#

You have requested a new password for #host#.

Please open the link below to set up your new password.

#link# 

If you did not request this email, please contact the website administrator.



---------------------------------

This is an auto-generated message. Do not answer please.
';

