<?php

declare(strict_types=1);

namespace App\Email\Infrastructure\Controller;

use App\Email\Application\Service\EmailVerificationService;
use App\User\Domain\Contract\UserRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class ResendVerificationController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly UserRepositoryInterface $userRepository,
        #[Autowire(service: 'limiter.resend_verification')]
        private readonly RateLimiterFactory $resendVerificationLimiter,
    ) {
    }

    #[Route('/api/email/resend-verification', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email || !is_string($email)) {
            return new JsonResponse(['error' => 'Email is required.'], Response::HTTP_BAD_REQUEST);
        }

        $ip = $request->getClientIp() ?? 'unknown';
        $limiter = $this->resendVerificationLimiter->create($ip);
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = (int) ceil($limit->getRetryAfter()->format('U.u') - microtime(true));
            return new JsonResponse([
                'error' => 'Too many requests. Please try again later.',
                'retryAfter' => max(1, $retryAfter),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $user = $this->userRepository->findByEmail($email);

        if (null === $user) {
            // Don't leak whether the email exists
            return new JsonResponse(['message' => 'If the email is registered and not verified, a verification email has been sent.'], Response::HTTP_ACCEPTED);
        }

        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Email already verified.'], Response::HTTP_ACCEPTED);
        }

        $this->emailVerificationService->sendVerificationEmail((string) $user->getId());

        return new JsonResponse(['message' => 'Verification email sent.'], Response::HTTP_ACCEPTED);
    }
}
