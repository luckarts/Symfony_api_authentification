<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use App\User\Domain\Contract\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/users/verified/count', name: 'app_user_count_verified', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CountVerifiedUsersController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'totalItems' => $this->userRepository->countVerified(),
        ]);
    }
}
