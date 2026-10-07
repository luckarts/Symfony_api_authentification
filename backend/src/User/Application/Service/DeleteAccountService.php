<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\User\Domain\Contract\UserCredentialsRevokerInterface;
use App\User\Domain\Contract\UserRepositoryInterface;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DeleteAccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserCredentialsRevokerInterface $credentialsRevoker,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function delete(User $user): void
    {
        $this->credentialsRevoker->revokeAll($user);
        $this->userRepository->remove($user);
        $this->entityManager->flush();
    }
}
