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

use Contao\BackendUser;
use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\BackendPasswordRecoveryBundle\InteractiveLogin\InteractiveBackendLogin;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class RenewPasswordController.
 *
 * @Route(defaults={"_scope" = "backend"})
 */
class RenewPasswordController extends AbstractController
{
    const CONTAO_LOG_CAT = 'BACKEND_PASSWORD_RECOVERY';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var InteractiveBackendLogin
     */
    private $interactiveBackendLogin;

    /**
     * @var Security
     */
    private $securityHelper;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(ContaoFramework $framework, Connection $connection, InteractiveBackendLogin $interactiveBackendLogin, Security $securityHelper, ?LoggerInterface $logger = null)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->interactiveBackendLogin = $interactiveBackendLogin;
        $this->securityHelper = $securityHelper;
        $this->logger = $logger;
    }

    /**
     * 1. Get Contao backend user from token
     * 2. Interactive login
     * 3. Set tl_user.pwChange to '1'
     * 4. Redirect to Contao native "password forgot controller".
     *
     * @Route("/backendpasswordrecovery/renewpassword/{token}", name="backend_password_recovery_renewpassword")
     *
     * @throws Exception
     */
    public function renewpasswordAction($token = null): Response
    {
        $this->initializeContaoFramework();

        // Check if token exists in the url -> empty('0') === true
        if (empty($token)) {
            return new Response('Acces denied due to missing or invalid token.', Response::HTTP_UNAUTHORIZED);
        }

        // Get user from token.
        $stmt = $this->connection->prepare('SELECT * FROM tl_user WHERE activation=? AND disable=? AND (start=? OR start<?) AND (stop=? OR stop>?) LIMIT 0,1');
        $stmt->bindValue(1, $token);
        $stmt->bindValue(2, '');
        $stmt->bindValue(3, '');
        $stmt->bindValue(4, time());
        $stmt->bindValue(5, '');
        $stmt->bindValue(6, time());
        $stmt->execute();

        $strErrorMsg = 'Backend user not found. Perhaps your token has expired. Please try to restore your password again.';

        if (!($arrUsers = $stmt->fetchAll())) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        $username = $arrUsers[0]['username'];

        // Interactive login
        if (!$this->interactiveBackendLogin->login($username)) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        // Get logged in backend user
        $user = $this->securityHelper->getUser();

        // Validate
        if (!$user instanceof BackendUser || $user->getUsername() !== $username) {
            return new Response($strErrorMsg, Response::HTTP_UNAVAILABLE_FOR_LEGAL_REASONS);
        }

        // Trigger Contao post login Hook
        if (!empty($GLOBALS['TL_HOOKS']['postLogin']) && \is_array($GLOBALS['TL_HOOKS']['postLogin'])) {
            @trigger_error('Using the "postLogin" hook has been deprecated and will no longer work in Contao 5.0.', E_USER_DEPRECATED);

            /** @var System $systemAdapter */
            $systemAdapter = $this->framework->getAdapter(System::class);

            foreach ($GLOBALS['TL_HOOKS']['postLogin'] as $callback) {
                $systemAdapter->importStatic($callback[0])->{$callback[1]}($user);
            }
        }

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        // Reset token, loginAttempts, etc.
        // and set pwChange to "1"
        // thats the way we can use the contao native "password forgot controller".
        $qb->update('tl_user', 'u')
            ->set('u.pwChange', ':pwChange')
            ->set('u.activation', ':activation')
            ->set('u.loginAttempts', ':loginAttempts')
            ->set('u.locked', ':locked')
            ->where('u.id = :id')
            ->setParameter('pwChange', '1', \PDO::PARAM_STR)
            ->setParameter('activation', '', \PDO::PARAM_STR)
            ->setParameter('loginAttempts', 0, \PDO::PARAM_INT)
            ->setParameter('locked', 0, \PDO::PARAM_INT)
            ->setParameter('id', (int) $user->id, \PDO::PARAM_INT)
        ;

        $qb->execute();

        // Log
        if ($this->logger) {
            $strText = sprintf('Backend user "%s" has recovered his password.', $username);
            $this->logger->log(
                LogLevel::INFO,
                $strText,
                ['contao' => new ContaoContext(__METHOD__, static::CONTAO_LOG_CAT)]
            );
        }

        // Redirects to the "contao_backend_password" route.
        return $this->redirectToRoute('contao_backend_password');
    }
}
