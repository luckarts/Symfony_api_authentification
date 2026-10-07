<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Service;

use App\User\Application\Service\DeleteAccountService;
use App\User\Domain\Contract\UserCredentialsRevokerInterface;
use App\User\Domain\Contract\UserRepositoryInterface;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('user')]
class DeleteAccountServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private UserCredentialsRevokerInterface&MockObject $credentialsRevoker;
    private EntityManagerInterface&MockObject $entityManager;
    private DeleteAccountService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->credentialsRevoker = $this->createMock(UserCredentialsRevokerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new DeleteAccountService(
            $this->userRepository,
            $this->credentialsRevoker,
            $this->entityManager,
        );
    }

    #[Test]
    public function it_revokes_credentials_then_removes_and_flushes(): void
    {
        $user = User::register('delete-me@example.com', 'hashed', 'John', 'Doe');

        $this->credentialsRevoker->expects($this->once())
            ->method('revokeAll')
            ->with($user);

        $this->userRepository->expects($this->once())
            ->method('remove')
            ->with($user);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->delete($user);
    }
}
