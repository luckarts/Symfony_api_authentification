<?php

declare(strict_types=1);

namespace App\Tests\E2E\Email;

use App\Tests\E2E\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

#[Group('e2e')]
#[Group('email')]
class EmailVerificationTest extends AbstractApiTestCase
{
    private const TEST_PASSWORD = 'T3st!P@ss#Api42';

    private string $token;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->email = 'verify-' . uniqid() . '@example.com';
        $this->token = $this->authenticate($this->email, self::TEST_PASSWORD);
    }

    #[Test]
    #[Group('smoke')]
    public function resend_verification_email_with_email_in_body(): void
    {
        $response = $this->apiRequest('POST', '/api/email/resend-verification', null, ['email' => $this->email]);
        $this->assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    #[Test]
    public function resend_verification_without_email_returns_400(): void
    {
        $response = $this->apiRequest('POST', '/api/email/resend-verification');
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function verify_with_missing_params_returns_400(): void
    {
        $this->client->request('GET', '/api/email/verify');
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
