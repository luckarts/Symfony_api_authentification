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

#[Group('e2e')]
#[Group('security')]
class LoginUserEnumerationTest extends WebTestCase
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
    public function unknown_email_and_wrong_password_are_indistinguishable(): void
    {
        $password = 'T3st!P@ss#Api42';
        $knownEmail = 'enumeration_known_' . uniqid() . '@example.com';
        $unknownEmail = 'enumeration_unknown_' . uniqid() . '@example.com';

        $this->registerUser($knownEmail, $password);

        $this->client->setServerParameter('REMOTE_ADDR', '10.10.0.1');
        $unknownResponse = $this->requestToken($unknownEmail, 'wrong_password');

        $this->client->setServerParameter('REMOTE_ADDR', '10.10.0.2');
        $knownResponse = $this->requestToken($knownEmail, 'wrong_password');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $unknownResponse->getStatusCode());
        $this->assertSame(Response::HTTP_BAD_REQUEST, $knownResponse->getStatusCode());

        $unknownBody = json_decode((string) $unknownResponse->getContent(), true);
        $knownBody = json_decode((string) $knownResponse->getContent(), true);

        $this->assertSame($unknownBody, $knownBody, 'Responses must not reveal whether the email is registered.');
        $this->assertSame('invalid_grant', $unknownBody['error']);
        $this->assertArrayNotHasKey('hint', $unknownBody);
    }

    #[Test]
    public function existing_unverified_email_is_not_revealed_without_valid_credentials(): void
    {
        $password = 'T3st!P@ss#Api42';
        $email = 'enumeration_unverified_' . uniqid() . '@example.com';

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
