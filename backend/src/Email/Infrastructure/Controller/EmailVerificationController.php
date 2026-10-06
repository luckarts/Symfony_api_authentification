<?php

declare(strict_types=1);

namespace App\Email\Infrastructure\Controller;

use App\Email\Application\Service\EmailVerificationService;
use App\User\Domain\Exception\UserNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\InvalidSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\WrongEmailVerifyException;

#[AsController]
class EmailVerificationController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly string $frontendUrl,
    ) {
    }

    #[Route('/api/email/verify', name: 'api_email_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $userId = $request->query->get('userId');
        $userEmail = $request->query->get('userEmail');

        if (null === $userId || null === $userEmail) {
            return $this->redirectToFrontend('error', 'missing_parameters');
        }

        try {
            $this->emailVerificationService->verify((string) $userId, (string) $userEmail, $request);

            return $this->redirectToFrontend('success');
        } catch (UserNotFoundException) {
            return $this->redirectToFrontend('error', 'user_not_found');
        } catch (ExpiredSignatureException) {
            return $this->redirectToFrontend('error', 'expired');
        } catch (WrongEmailVerifyException) {
            return $this->redirectToFrontend('error', 'wrong_email');
        } catch (InvalidSignatureException) {
            return $this->redirectToFrontend('error', 'invalid_signature');
        }
    }

    private function redirectToFrontend(string $status, ?string $reason = null): RedirectResponse
    {
        $params = ['status' => $status];

        if (null !== $reason) {
            $params['reason'] = $reason;
        }

        return new RedirectResponse(sprintf(
            '%s/auth/verify-email?%s',
            rtrim($this->frontendUrl, '/'),
            http_build_query($params),
        ));
    }
}
