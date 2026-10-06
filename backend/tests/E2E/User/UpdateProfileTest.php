<?php

declare(strict_types=1);

namespace App\Tests\E2E\User;

use App\Tests\E2E\AbstractApiTestCase;
use App\User\Domain\Contract\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class UpdateProfileTest extends AbstractApiTestCase
{
    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('user')]
    public function update_profile_success(): void
    {
        $email = 'update_' . uniqid() . '@example.com';
        $password = 'T3st!P@ss#Api42';

        $user = $this->createUser($email, $password, 'John', 'Doe');
        $token = $this->getOAuth2Token($email, $password);

        $response = $this->apiRequest('PUT', sprintf('/api/users/%s/profile', $user->getId()), $token, [
            'firstName' => 'Updated',
            'lastName' => 'Name',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Updated', $data['firstName']);
        $this->assertSame('Name', $data['lastName']);
        $this->assertSame($email, $data['email']);
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function update_profile_without_token_returns_401(): void
    {
        $email = 'update_noauth_' . uniqid() . '@example.com';
        $user = $this->createUser($email, 'T3st!P@ss#Api42', 'John', 'Doe');

        $response = $this->apiRequest('PUT', sprintf('/api/users/%s/profile', $user->getId()), null, [
            'firstName' => 'Updated',
            'lastName' => 'Name',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function update_profile_cannot_escalate_roles_or_verification(): void
    {
        $email = 'update_escalate_' . uniqid() . '@example.com';
        $password = 'T3st!P@ss#Api42';
        $user = $this->createUser($email, $password, 'John', 'Doe');
        $token = $this->getOAuth2Token($email, $password);

        $response = $this->apiRequest('PUT', sprintf('/api/users/%s/profile', $user->getId()), $token, [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'roles' => ['ROLE_ADMIN'],
            'isVerified' => true,
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame(['ROLE_USER'], $data['roles']);
        $this->assertFalse($data['isVerified']);

        /** @var UserRepositoryInterface $userRepository */
        $userRepository = static::getContainer()->get(UserRepositoryInterface::class);
        $reloaded = $userRepository->findById((string) $user->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(['ROLE_USER'], $reloaded->getRoles());
        $this->assertFalse($reloaded->isVerified());
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function update_profile_with_another_user_id_returns_404(): void
    {
        $email = 'update_idor_' . uniqid() . '@example.com';
        $password = 'T3st!P@ss#Api42';
        $this->createUser($email, $password, 'John', 'Doe');
        $token = $this->getOAuth2Token($email, $password);

        $other = $this->createUser('update_other_' . uniqid() . '@example.com', $password, 'Other', 'User');

        $response = $this->apiRequest('PUT', sprintf('/api/users/%s/profile', $other->getId()), $token, [
            'firstName' => 'Hacked',
            'lastName' => 'Name',
        ]);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
