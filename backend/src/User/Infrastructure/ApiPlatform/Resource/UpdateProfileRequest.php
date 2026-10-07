<?php

declare(strict_types=1);

namespace App\User\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateProfileRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $lastName = '';
}
