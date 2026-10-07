<?php

declare(strict_types=1);

namespace App\Tests\E2E\User;

use App\Tests\E2E\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class ListVerifiedUsersTest extends AbstractApiTestCase
{
    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('user')]
    public function it_returns_only_verified_first_names(): void
    {
        $password = 'T3st!P@ss#Api42';
        $verifiedFirstName = 'Verified' . substr(uniqid(), -6);
        $unverifiedFirstName = 'Hidden' . substr(uniqid(), -6);

        $verified = $this->createUser(
            'verified_list_' . uniqid() . '@example.com',
            $password,
            $verifiedFirstName,
            'Doe',
        );
        $verified->verify();

        $this->createUser(
            'unverified_list_' . uniqid() . '@example.com',
            $password,
            $unverifiedFirstName,
            'Doe',
        );

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        $token = $this->getOAuth2Token((string) $verified->getEmail(), $password);

        $response = $this->apiRequest('GET', '/api/v1/users/verified', $token);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        /** @var array{member: list<string>, totalItems: int} $data */
        $data = json_decode((string) $response->getContent(), true);

        $this->assertContains($verifiedFirstName, $data['member']);
        $this->assertNotContains($unverifiedFirstName, $data['member']);
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString($verified->getEmail(), $content);
        $this->assertSame(
            ['member', 'totalItems', 'page', 'itemsPerPage'],
            array_keys($data),
            'The response must not expose any other user field (email, lastName, id...).',
        );
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function it_requires_authentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/users/verified');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
