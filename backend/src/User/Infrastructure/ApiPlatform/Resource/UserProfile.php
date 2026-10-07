<?php

declare(strict_types=1);

namespace App\User\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\ApiPlatform\State\Processor\UpdateProfileProcessor;
use App\User\Infrastructure\ApiPlatform\State\Provider\ProfileProvider;

#[
    ApiResource(
        shortName: "UserProfile",
        operations: [
            new Get(
                uriTemplate: "/users/me",
                provider: ProfileProvider::class,
                openapi: new Operation(summary: 'Get current user profile', security: [['BearerAuth' => []]]),
            ),
            new Get(
                uriTemplate: "/users/{id}",
                provider: ProfileProvider::class,
                openapi: new Operation(security: [['BearerAuth' => []]]),
            ),
            new Put(
                uriTemplate: "/users/{id}/profile",
                provider: ProfileProvider::class,
                processor: UpdateProfileProcessor::class,
                input: UpdateProfileRequest::class,
                output: UserProfile::class,
                openapi: new Operation(security: [['BearerAuth' => []]]),
            ),
        ],
        routePrefix: "/api",
    ),
]
class UserProfile
{
    #[ApiProperty(writable: false)]
    public string $id = "";

    #[ApiProperty(writable: false)]
    public string $email = "";

    public string $firstName = "";
    public string $lastName = "";

    /** @var list<string> */
    #[ApiProperty(writable: false)]
    public array $roles = [];

    #[ApiProperty(writable: false)]
    public bool $isVerified = false;

    #[ApiProperty(writable: false)]
    public string $createdAt = "";

    public static function fromEntity(User $user): self
    {
        $profile = new self();
        $profile->id = (string) $user->getId();
        $profile->email = $user->getEmail();
        $profile->firstName = $user->getFirstName();
        $profile->lastName = $user->getLastName();
        $profile->roles = $user->getRoles();
        $profile->isVerified = $user->isVerified();
        $profile->createdAt = $user
            ->getCreatedAt()
            ->format(\DateTimeInterface::ATOM);

        return $profile;
    }
}
