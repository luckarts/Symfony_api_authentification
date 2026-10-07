<?php

declare(strict_types=1);

namespace App\User\Infrastructure\OAuth2;

use App\User\Domain\Contract\UserCredentialsRevokerInterface;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Security\SecurityUser;
use League\Bundle\OAuth2ServerBundle\Service\CredentialsRevokerInterface;

final class DoctrineUserCredentialsRevoker implements UserCredentialsRevokerInterface
{
    public function __construct(
        private readonly CredentialsRevokerInterface $credentialsRevoker,
    ) {
    }

    public function revokeAll(User $user): void
    {
        $this->credentialsRevoker->revokeCredentialsForUser(new SecurityUser($user));
    }
}
