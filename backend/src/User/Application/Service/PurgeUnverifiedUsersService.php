<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\User\Application\Dto\PurgeReport;
use App\User\Domain\Contract\UserCredentialsRevokerInterface;
use App\User\Domain\Contract\UserRepositoryInterface;
use App\User\Domain\Entity\User;
use App\User\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class PurgeUnverifiedUsersService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserCredentialsRevokerInterface $credentialsRevoker,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function purge(\DateInterval $minAge, int $limit, bool $dryRun = true): PurgeReport
    {
        $now = $this->clock->now();
        $before = $now->sub($minAge);

        if ($before >= $now) {
            throw new \InvalidArgumentException('The minimum account age must be a strictly positive duration.');
        }

        $candidates = $this->userRepository->findUnverifiedBefore($before, $limit);

        $purgedEmails = [];
        $skippedEmails = [];

        foreach ($candidates as $user) {
            if ($this->isProtected($user)) {
                $skippedEmails[] = $user->getEmail();

                continue;
            }

            $purgedEmails[] = $user->getEmail();

            if ($dryRun) {
                continue;
            }

            $this->credentialsRevoker->revokeAll($user);
            $this->userRepository->remove($user);
        }

        if (!$dryRun && [] !== $purgedEmails) {
            $this->entityManager->flush();
        }

        return new PurgeReport(
            before: $before,
            candidates: count($candidates),
            purgedEmails: $purgedEmails,
            skippedEmails: $skippedEmails,
        );
    }

    private function isProtected(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array(Role::ROLE_ADMIN->value, $roles, true)
            || in_array(Role::ROLE_SUPER_ADMIN->value, $roles, true);
    }
}
