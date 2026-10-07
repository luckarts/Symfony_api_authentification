<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use App\User\Domain\Contract\UserRepositoryInterface;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/users/verified', name: 'app_user_list_verified', methods: ['GET'])]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ListVerifiedUsersController
{
    private const DEFAULT_ITEMS_PER_PAGE = 50;
    private const MAX_ITEMS_PER_PAGE = 100;

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = (int) $request->query->get('itemsPerPage', self::DEFAULT_ITEMS_PER_PAGE);
        $itemsPerPage = min(max(1, $itemsPerPage), self::MAX_ITEMS_PER_PAGE);

        $collection = $this->userRepository->findVerifiedPaginated($page, $itemsPerPage);

        $firstNames = array_map(
            static fn (User $user): string => $user->getFirstName(),
            $collection->getItems(),
        );

        return new JsonResponse([
            'member' => array_values($firstNames),
            'totalItems' => $collection->getTotalItems(),
            'page' => $collection->getCurrentPage(),
            'itemsPerPage' => $collection->getItemsPerPage(),
        ]);
    }
}
