<?php

/**
 * @copyright  Marko Cupic 2020 <m.cupic@gmx.ch>
 * @author     Marko Cupic
 * @package    Office365Bundle for Schule Ettiswil
 * @license    MIT
 * @see        https://github.com/markocupic/office365-bundle
 *
 */

namespace Markocupic\Office365Bundle\EventListener\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Input;
use Contao\MemberModel;
use Contao\UserModel;
use Contao\Validator;
use Symfony\Component\HttpFoundation\RequestStack;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Terminal42\ServiceAnnotationBundle\ServiceAnnotationInterface;

/**
 * Class ImportUserListener
 * @package Markocupic\Office365Bundle\EventListener\ContaoHooks
 */
class ImportUserListener implements ServiceAnnotationInterface
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * ImportUserListener constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
    }

    /**
     * Allow users and members to login with their email address
     *
     * @Hook("importUser")
     * @param $strUsername
     * @param $strPassword
     * @param $strTable
     * @return bool
     */
    public function onImportUser($strUsername, $strPassword, $strTable): bool
    {
        if ($strTable === 'tl_user')
        {
            /** @var UserModel $userModelAdapter */
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        }
        elseif ($strTable === 'tl_member')
        {
            /** @var MemberModel $userModelAdapter */
            $userModelAdapter = $this->framework->getAdapter(MemberModel::class);
        }

        if ($userModelAdapter !== null)
        {
            if (strtolower(trim($strUsername)) !== '' && Validator::isEmail(strtolower(trim($strUsername))))
            {
                $strEmail = strtolower(trim($strUsername));

                $objUser = $userModelAdapter->findOneByEmail($strEmail);
                if ($objUser !== null)
                {
                    /** @var Input $inputAdapter */
                    $inputAdapter = $this->framework->getAdapter(Input::class);

                    $request = $this->requestStack->getCurrentRequest();

                    /** @var Request $request */
                    $request->request->set('username', $objUser->username);

                    // Used for backend login
                    $inputAdapter->setPost('username', $objUser->username);

                    return true;
                }
            }
        }

        return false;
    }

}
