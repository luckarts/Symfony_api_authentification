<?php

declare(strict_types=1);

namespace App\User\Domain\Contract;

use App\User\Domain\Entity\User;

interface UserCredentialsRevokerInterface
{
    public function revokeAll(User $user): void;
}
