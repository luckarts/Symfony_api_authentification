<?php

declare(strict_types=1);

namespace App\User\Infrastructure\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Application\Command\UpdateProfileCommand;
use App\User\Application\Service\UpdateProfileService;
use App\User\Infrastructure\ApiPlatform\Resource\UpdateProfileRequest;
use App\User\Infrastructure\ApiPlatform\Resource\UserProfile;
use App\User\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<UpdateProfileRequest, UserProfile>
 */
class UpdateProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UpdateProfileService $updateProfileService,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserProfile
    {
        // @phpstan-ignore-next-line instanceof.alwaysTrue
        assert($data instanceof UpdateProfileRequest);

        /** @var SecurityUser $securityUser */
        $securityUser = $this->security->getUser();
        $user = $securityUser->getUser();

        $targetId = $uriVariables['id'] ?? null;
        if (null !== $targetId && (string) $user->getId() !== (string) $targetId) {
            throw new NotFoundHttpException('User not found.');
        }

        $user = $this->updateProfileService->update($user, new UpdateProfileCommand(
            firstName: $data->firstName,
            lastName: $data->lastName,
        ));

        return UserProfile::fromEntity($user);
    }
}
