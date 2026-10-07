<?php

declare(strict_types=1);

namespace App\Tests\E2E\User;

use App\Tests\E2E\AbstractApiTestCase;
use App\User\Domain\Contract\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class DeleteAccountTest extends AbstractApiTestCase
{
    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('user')]
    public function authenticated_user_can_delete_own_account(): void
    {
        $email = 'delete_me_' . uniqid() . '@example.com';
        $password = 'T3st!P@ss#Api42';

        $this->createUser($email, $password, 'John', 'Doe');
        $token = $this->getOAuth2Token($email, $password);

        $response = $this->apiRequest('DELETE', '/api/v1/users/me', $token);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->apiRequest('GET', '/api/users/me', $token)->getStatusCode(),
            'The revoked token must no longer grant access.',
        );

        /** @var UserRepositoryInterface $userRepository */
        $userRepository = static::getContainer()->get(UserRepositoryInterface::class);
        $this->assertNull($userRepository->findByEmail($email), 'The account must be deleted.');
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function delete_account_without_token_returns_401(): void
    {
        $response = $this->apiRequest('DELETE', '/api/v1/users/me');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
