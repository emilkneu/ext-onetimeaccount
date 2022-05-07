<?php

declare(strict_types=1);

namespace OliverKlee\Onetimeaccount\Controller;

use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUser;
use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUserGroup;
use OliverKlee\FeUserExtraFields\Domain\Repository\FrontendUserGroupRepository;
use OliverKlee\FeUserExtraFields\Domain\Repository\FrontendUserRepository;
use OliverKlee\Onetimeaccount\Service\CredentialsGenerator;
use OliverKlee\Onetimeaccount\Validation\UserValidator;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Base class to implement most of the functionality of the plugin except for the specifics of what should
 * happen after a user has been created (autologin or storing the user UID in the session).
 */
abstract class AbstractUserController extends ActionController
{
    /**
     * @var FrontendUserRepository
     */
    protected $userRepository;

    /**
     * @var FrontendUserGroupRepository
     */
    protected $userGroupRepository;

    /**
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @var CredentialsGenerator
     */
    protected $credentialsGenerator;

    /**
     * @var UserValidator
     */
    protected $userValidator;

    public function injectFrontendUserRepository(FrontendUserRepository $repository): void
    {
        $this->userRepository = $repository;
    }

    public function injectFrontendUserGroupRepository(FrontendUserGroupRepository $repository): void
    {
        $this->userGroupRepository = $repository;
    }

    public function injectPersistenceManager(PersistenceManagerInterface $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function injectCredentialsGenerator(CredentialsGenerator $generator): void
    {
        $this->credentialsGenerator = $generator;
    }

    public function injectUserValidator(UserValidator $validator): void
    {
        $this->userValidator = $validator;
    }

    /**
     * Creates the user creation form (which initially is empty).
     *
     * @TYPO3\CMS\Extbase\Annotation\IgnoreValidation("user")
     */
    public function newAction(?FrontendUser $user = null): void
    {
        $newUser = ($user instanceof FrontendUser) ? $user : new FrontendUser();

        $this->view->assign('user', $newUser);
    }

    public function initializeCreateAction(): void
    {
        if (!$this->arguments->hasArgument('user')) {
            return;
        }

        $userValidator = $this->userValidator;
        $userValidator->setSettings($this->settings);
        $this->arguments->getArgument('user')->setValidator($userValidator);
    }

    /**
     * Creates and persists a new user.
     *
     * Note: `$user` is optional in order to avoid a crash when someone is using a FE login form on the sane page
     * after creating a user with this action. (This will use the current URL as form target, causing the user to be
     * null as it had been sent via a POST request.)
     *
     * @throws \RuntimeException
     */
    public function createAction(?FrontendUser $user = null): void
    {
        if (!$user instanceof FrontendUser) {
            return;
        }

        $plaintextPassword = $this->enrichUser($user);
        if (!\is_string($plaintextPassword)) {
            throw new \RuntimeException('Could not generate user credentials.', 1651673684);
        }

        $this->userRepository->add($user);
        $this->persistenceManager->persistAll();

        $this->afterCreate($user, $plaintextPassword);
    }

    /**
     * This method will be executed as the last step of `createAction`.
     */
    abstract protected function afterCreate(FrontendUser $user, string $plaintextPassword): void;

    /**
     * Adds data from the configuration to the user before it can be saved.
     *
     * @return string the plaintext password, or null if no new password chould be generated
     */
    private function enrichUser(FrontendUser $user): ?string
    {
        $this->credentialsGenerator->generateUsernameForUser($user);
        $password = $this->credentialsGenerator->generatePasswordForUser($user);

        $this->enrichWithPid($user);
        $this->enrichWithGroups($user);

        return $password;
    }

    private function enrichWithPid(FrontendUser $user): void
    {
        $pageUid = $this->settings['systemFolderForNewUsers'] ?? null;
        if (\is_numeric($pageUid)) {
            $user->setPid((int)$pageUid);
        }
    }

    private function enrichWithGroups(FrontendUser $user): void
    {
        $userGroupSetting = $this->settings['groupsForNewUsers'] ?? null;
        $userGroupUids = \is_string($userGroupSetting) ? GeneralUtility::intExplode(',', $userGroupSetting, true) : [];
        foreach ($userGroupUids as $uid) {
            $group = $this->userGroupRepository->findByUid($uid);
            if ($group instanceof FrontendUserGroup) {
                $user->addUserGroup($group);
            }
        }
    }
}
