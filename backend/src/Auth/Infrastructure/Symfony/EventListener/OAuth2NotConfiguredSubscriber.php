<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Symfony\EventListener;

use App\Auth\Domain\Exception\OAuth2NotConfiguredException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception')]
final class OAuth2NotConfiguredSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof OAuth2NotConfiguredException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            data: [
                'error' => 'service_unavailable',
                'error_description' => $exception->getMessage(),
            ],
            status: 503,
        ));
    }
}