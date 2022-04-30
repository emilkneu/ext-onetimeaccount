<?php

declare(strict_types=1);

namespace OliverKlee\Onetimeaccount\Tests\Unit\Controller;

use OliverKlee\FeUserExtraFields\Domain\Model\FrontendUser;
use OliverKlee\FeUserExtraFields\Domain\Repository\FrontendUserRepository;
use OliverKlee\Onetimeaccount\Controller\UserWithAutologinController;
use PHPUnit\Framework\MockObject\MockObject;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Fluid\View\TemplateView;
use TYPO3\TestingFramework\Core\AccessibleObjectInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @covers \OliverKlee\Onetimeaccount\Controller\UserWithAutologinController
 * @covers \OliverKlee\Onetimeaccount\Controller\AbstractUserController
 */
final class UserWithAutologinControllerTest extends UnitTestCase
{
    /**
     * @var UserWithAutologinController&MockObject&AccessibleObjectInterface
     *
     * We can make this property private once we drop support for TYPO3 V9.
     */
    protected $subject;

    /**
     * @var ObjectProphecy<TemplateView>
     *
     * We can make this property private once we drop support for TYPO3 V9.
     */
    protected $viewProphecy;

    /**
     * @var ObjectProphecy<FrontendUserRepository>
     *
     * We can make this property private once we drop support for TYPO3 V9.
     */
    protected $userRepositoryProphecy;

    protected function setUp(): void
    {
        parent::setUp();

        // We need to create an accessible mock in order to be able to set the protected `view`.
        // We can drop the additional arguments to skip the original constructor once we drop support for TYPO3 V9.
        $this->subject
            = $this->getAccessibleMock(UserWithAutologinController::class, ['redirect', 'forward'], [], '', false);

        $this->viewProphecy = $this->prophesize(TemplateView::class);
        $view = $this->viewProphecy->reveal();
        $this->subject->_set('view', $view);

        $this->userRepositoryProphecy = $this->prophesize(FrontendUserRepository::class);
        $userRepository = $this->userRepositoryProphecy->reveal();
        $this->subject->injectFrontendUserRepository($userRepository);
    }

    /**
     * @test
     */
    public function isActionController(): void
    {
        self::assertInstanceOf(ActionController::class, $this->subject);
    }

    /**
     * @test
     */
    public function newActionWithUserPassesUserToView(): void
    {
        $user = new FrontendUser();

        $this->viewProphecy->assign('user', $user)->shouldBeCalled();

        $this->subject->newAction($user);
    }

    /**
     * @test
     */
    public function newActionWithoutUserPassesVirginUserToView(): void
    {
        $this->viewProphecy->assign('user', Argument::type(FrontendUser::class))->shouldBeCalled();

        $this->subject->newAction();
    }

    /**
     * @test
     */
    public function newActionWithNullUserPassesVirginUserToView(): void
    {
        $this->viewProphecy->assign('user', Argument::type(FrontendUser::class))->shouldBeCalled();

        $this->subject->newAction(null);
    }

    /**
     * @test
     */
    public function createActionAddsProvidedUserToRepository(): void
    {
        $user = new FrontendUser();
        $this->userRepositoryProphecy->add($user)->shouldBeCalled();

        $this->subject->createAction($user);
    }
}
