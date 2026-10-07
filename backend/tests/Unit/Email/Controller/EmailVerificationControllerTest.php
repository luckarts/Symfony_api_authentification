<?php

declare(strict_types=1);

namespace App\Tests\Unit\Email\Controller;

use App\Email\Application\Service\EmailVerificationService;
use App\Email\Infrastructure\Controller\EmailVerificationController;
use App\User\Domain\Exception\UserNotFoundException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\WrongEmailVerifyException;

#[Group('unit')]
#[Group('email')]
class EmailVerificationControllerTest extends TestCase
{
    private const FRONTEND_URL = 'https://app.example.test';

    private EmailVerificationService&MockObject $emailVerificationService;
    private EmailVerificationController $controller;

    protected function setUp(): void
    {
        $this->emailVerificationService = $this->createMock(EmailVerificationService::class);
        $this->controller = new EmailVerificationController($this->emailVerificationService, self::FRONTEND_URL);
    }

    #[Test]
    public function verify_redirects_to_frontend_error_on_user_not_found(): void
    {
        $request = new Request(['userId' => 'missing-user', 'userEmail' => 'test@example.com']);

        $this->emailVerificationService
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(UserNotFoundException::withId('missing-user'));

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString(
            self::FRONTEND_URL.'/auth/verify-email?status=error&reason=user_not_found',
            (string) $response->headers->get('Location'),
        );
    }

    #[Test]
    public function verify_redirects_to_frontend_error_on_expired_signature(): void
    {
        $request = new Request(['userId' => 'user-id', 'userEmail' => 'test@example.com']);

        $this->emailVerificationService
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(new ExpiredSignatureException());

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString(
            self::FRONTEND_URL.'/auth/verify-email?status=error&reason=expired',
            (string) $response->headers->get('Location'),
        );
    }

    #[Test]
    public function verify_redirects_to_frontend_error_on_invalid_signature(): void
    {
        $request = new Request(['userId' => 'user-id', 'userEmail' => 'test@example.com']);

        $this->emailVerificationService
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(new InvalidSignatureException());

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString(
            self::FRONTEND_URL.'/auth/verify-email?status=error&reason=invalid_signature',
            (string) $response->headers->get('Location'),
        );
    }

    #[Test]
    public function verify_redirects_to_frontend_error_on_wrong_email(): void
    {
        $request = new Request(['userId' => 'user-id', 'userEmail' => 'test@example.com']);

        $this->emailVerificationService
            ->expects($this->once())
            ->method('verify')
            ->willThrowException(new WrongEmailVerifyException());

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString(
            self::FRONTEND_URL.'/auth/verify-email?status=error&reason=wrong_email',
            (string) $response->headers->get('Location'),
        );
    }

    #[Test]
    public function verify_redirects_to_frontend_success(): void
    {
        $request = new Request(['userId' => 'user-id', 'userEmail' => 'test@example.com']);

        $this->emailVerificationService
            ->expects($this->once())
            ->method('verify');

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame(
            self::FRONTEND_URL.'/auth/verify-email?status=success',
            $response->headers->get('Location'),
        );
    }

    #[Test]
    public function verify_redirects_to_frontend_error_on_missing_parameters(): void
    {
        $request = new Request([]);

        $this->emailVerificationService->expects($this->never())->method('verify');

        $response = $this->controller->verify($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertStringContainsString(
            self::FRONTEND_URL.'/auth/verify-email?status=error&reason=missing_parameters',
            (string) $response->headers->get('Location'),
        );
    }
}
