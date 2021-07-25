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

namespace Markocupic\BackendPasswordRecoveryBundle\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Email;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Message;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class PasswordRecoveryLinkRequestController.
 *
 * @Route(defaults={"_scope" = "backend", "_token_check" = true})
 * @internal
 */
class PasswordRecoveryLinkRequestController extends AbstractController
{

    /**
     * @var UriSigner
     */
    private $uriSigner;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TranslatorInterface
     */
    private $translator;



    public function __construct(UriSigner $uriSigner, RequestStack $requestStack, RouterInterface $router, TranslatorInterface $translator)
    {
        $this->uriSigner = $uriSigner;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->translator = $translator;
    }

    /**
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink/form", name="backend_password_recovery_requirepasswordrecoverylink_form")
     */
    public function requirepasswordrecoverylinkAction(): Response
    {
        $this->initializeContaoFramework();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        if ('tl_require_password_link_form' === $request->request->get('FORM_SUBMIT') && '' !== $request->request->get('usernameOrEmail')) {
            $usernameOrEmail = $request->request->get('usernameOrEmail');
            $time = time();

            $objUser = Database::getInstance()
                ->prepare("SELECT * FROM tl_user WHERE (email LIKE ? OR username=?) AND disable='' AND (start='' OR start<$time) AND (stop='' OR stop>$time)")
                ->limit(1)
                ->execute($usernameOrEmail, $usernameOrEmail)
            ;

            if (!$objUser->numRows) {
                Message::addError($this->translator->trans('ERR.pwRecoveryFailed',[], 'contao_default'));
            } else {
                // Set renew password token
                $token = md5(uniqid((string) mt_rand(), true));

                // Write token to db
                Database::getInstance()
                    ->prepare('UPDATE tl_user SET activation=? WHERE id=?')
                    ->execute($token, $objUser->id)
                ;

                // Generate renew password link
                $strLink = $this->router->generate(
                    'backend_password_recovery_renewpassword',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                // Send email with password recover link to the user
                $objEmail = new Email();
                $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];

                // Subject
                $strSubject = str_replace('#host#', Environment::get('base'), $this->translator->trans('MSC.pwRecoveryEmailSubject',[], 'contao_default'));
                $objEmail->subject = $strSubject;

                // Text
                $strText = str_replace('#host#', Environment::get('base'), $this->translator->trans('MSC.pwRecoveryEmailText',[], 'contao_default'));
                $strText = str_replace('#link#', $strLink, $strText);
                $strText = str_replace('#name#', $objUser->name, $strText);
                $objEmail->text = $strText;

                // Send
                $objEmail->sendTo($objUser->email);

                // Everything ok! We sign the uri & redirect to the confirmation page
                $href = $this->router->generate(
                    'backend_password_recovery_requirepasswordrecoverylink_confirm',
                    [],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                return $this->redirect($this->uriSigner->sign($href));
            }
        }

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showForm = true;
        $this->setUpTemplate($objTemplate);

        return $objTemplate->getResponse();
    }

    /**
     * @Route("/backendpasswordrecovery/requirepasswordrecoverylink/confirm", name="backend_password_recovery_requirepasswordrecoverylink_confirm")
     */
    public function requirepasswordrecoveryConfirmAction(): Response
    {
        $this->initializeContaoFramework();

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->uriSigner->check($request->getUri())) {
            return new Response('Access denied!', Response::HTTP_FORBIDDEN);
        }

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        $objTemplate = new BackendTemplate('be_password_recovery_link_request');
        $objTemplate->showConfirmation = true;
        $objTemplate->backHref = $this->router->generate('contao_backend_login');
        $this->setUpTemplate($objTemplate);

        return $objTemplate->getResponse();
    }

    private function setUpTemplate(BackendTemplate &$objTemplate): void
    {
        $objTemplate->theme = Backend::getTheme();
        $objTemplate->messages = Message::generate();
        $objTemplate->base = Environment::get('base');
        $objTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $objTemplate->host = Backend::getDecodedHostname();
        $objTemplate->charset = Config::get('characterSet');
    }
}
