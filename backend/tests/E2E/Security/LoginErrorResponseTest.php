<?php

declare(strict_types=1);

namespace App\Tests\E2E\Security;

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The password grant distinguishes "unknown email" from "wrong password" so
 * the frontend can route the user to signup.
 *
 * This deliberately trades away the anti-enumeration property locked by PR #33:
 * the signup endpoint already reveals whether an email is registered (409).
 */
#[Group('e2e')]
#[Group('security')]
class LoginErrorResponseTest extends WebTestCase
{
    private const CLIENT_ID = 'test_client';
    private const CLIENT_SECRET = 'test_secret';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        /** @var ClientManagerInterface $clientManager */
        $clientManager = static::getContainer()->get(ClientManagerInterface::class);
        if ($clientManager->find(self::CLIENT_ID) === null) {
            $oauthClient = new Client('Test Client', self::CLIENT_ID, self::CLIENT_SECRET);
            $oauthClient->setGrants(new Grant('password'), new Grant('refresh_token'));
            $oauthClient->setScopes(new Scope('email'), new Scope('profile'));
            $clientManager->save($oauthClient);
        }
    }

    #[Test]
    public function unknown_email_returns_user_not_found(): void
    {
        $unknownEmail = 'login_unknown_' . uniqid() . '@example.com';

        $this->client->setServerParameter('REMOTE_ADDR', '10.10.0.1');
        $response = $this->requestToken($unknownEmail, 'whatever_password');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('user_not_found', $body['error']);
    }

    #[Test]
    public function wrong_password_returns_invalid_grant(): void
    {
        $password = 'T3st!P@ss#Api42';
        $email = 'login_wrong_' . uniqid() . '@example.com';

        $this->registerUser($email, $password);

        $this->client->setServerParameter('REMOTE_ADDR', '10.10.0.2');
        $response = $this->requestToken($email, 'wrong_password');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('invalid_grant', $body['error']);
    }

    #[Test]
    public function existing_unverified_email_is_not_revealed_without_valid_credentials(): void
    {
        $password = 'T3st!P@ss#Api42';
        $email = 'login_unverified_' . uniqid() . '@example.com';

        $this->registerUser($email, $password);

        $this->client->setServerParameter('REMOTE_ADDR', '10.10.0.3');
        $response = $this->requestToken($email, 'wrong_password');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('invalid_grant', $body['error']);
        $this->assertStringNotContainsStringIgnoringCase('verify', (string) ($body['error_description'] ?? ''));
        $this->assertStringNotContainsStringIgnoringCase('verified', (string) ($body['error_description'] ?? ''));
    }

    private function requestToken(string $email, string $password): Response
    {
        $this->client->request('POST', '/oauth2/token', [
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'username' => $email,
            'password' => $password,
            'scope' => 'email',
        ]);

        return $this->client->getResponse();
    }

    private function registerUser(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/ld+json'],
            json_encode([
                'email' => $email,
                'password' => $password,
                'firstName' => 'Jane',
                'lastName' => 'Doe',
            ]),
        );

        if ($this->client->getResponse()->getStatusCode() !== Response::HTTP_CREATED) {
            throw new \RuntimeException(sprintf(
                'User registration failed: %d %s',
                $this->client->getResponse()->getStatusCode(),
                $this->client->getResponse()->getContent(),
            ));
        }
    }
}
