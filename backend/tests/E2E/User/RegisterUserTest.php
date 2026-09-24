<?php

declare(strict_types=1);

namespace App\Tests\E2E\User;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class RegisterUserTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->setupOAuthClient();
    }

    private function setupOAuthClient(): void
    {
        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        if ($clientManager->find('test_client') === null) {
            $oauthClient = new Client('Test Client', 'test_client', 'test_secret');
            $oauthClient->setGrants(new Grant('password'), new Grant('refresh_token'));
            $oauthClient->setScopes(new Scope('email'), new Scope('profile'));
            $clientManager->save($oauthClient);
        }
    }

    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('user')]
    public function register_success(): void
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json'];
        $payload = [
            'email' => 'user_' . uniqid() . '@example.com',
            'password' => 'T3st!P@ss#Api42',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];
        $response = $this->client->request('POST', '/api/users',[], [], $headers, json_encode($payload));
        $response = $this->client->getResponse();
       
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        // Assertions sur le contenu
        $this->assertArrayHasKey('email', $data);
        $this->assertSame($payload['email'], $data['email']);
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function register_with_duplicate_email_returns_409(): void
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json'];
        $payload = [
            'email' => 'duplicate_' . uniqid() . '@example.com',
            'password' => 'T3st!P@ss#Api42',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        $this->client->request('POST', '/api/users', [], [], $headers, json_encode($payload));
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', '/api/users', [], [], $headers, json_encode($payload));
        $this->assertSame(Response::HTTP_CONFLICT, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[Group('e2e')]
    #[Group('user')]
    public function register_with_weak_password_returns_422(): void
    {
        $headers = ['CONTENT_TYPE' => 'application/ld+json'];
        $payload = [
            'email' => 'user_' . uniqid() . '@example.com',
            'password' => 'password123',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];
        $this->client->request('POST', '/api/users', [], [], $headers, json_encode($payload));

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
    }
}
