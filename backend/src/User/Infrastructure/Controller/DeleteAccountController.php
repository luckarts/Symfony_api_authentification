<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use App\User\Application\Service\DeleteAccountService;
use App\User\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DeleteAccountController extends AbstractController
{
    public function __construct(
        private readonly DeleteAccountService $deleteAccountService,
    ) {
    }

    #[Route('/api/v1/users/me', name: 'app_user_delete_account', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();

        $this->deleteAccountService->delete($securityUser->getUser());

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
