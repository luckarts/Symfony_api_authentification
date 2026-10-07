<?php

declare(strict_types=1);

namespace App\Tests\E2E\User;

use App\Tests\E2E\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class VerifiedUsersCountTest extends AbstractApiTestCase
{
    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('user')]
    public function it_returns_the_number_of_verified_accounts(): void
    {
        $password = 'T3st!P@ss#Api42';

        $verified = $this->createUser(
            'count_verified_' . uniqid() . '@example.com',
            $password,
            'Alice',
            'Doe',
        );
        $verified->verify();

        $this->createUser(
            'count_unverified_' . uniqid() . '@example.com',
            $password,
            'Bob',
            'Doe',
        );

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        $token = $this->getOAuth2Token((string) $verified->getEmail(), $password);

        $response = $this->apiRequest('GET', '/api/v1/users/verified/count', $token);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{totalItems: int} $data */
        $data = json_decode((string) $response->getContent(), true);

        $this->assertSame(['totalItems'], array_keys($data), 'Only the count must be exposed.');
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString('Alice', $content);
        $this->assertStringNotContainsString((string) $verified->getEmail(), $content);
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function it_requires_authentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/users/verified/count');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
