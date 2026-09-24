<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

final class OAuth2NotConfiguredException extends \RuntimeException
{
    public static function noActiveClient(): self
    {
        return new self(
            'OAuth2 is not fully configured: no active client found. '
            . 'Run "php bin/console app:oauth:setup-client" to create the default OAuth2 client.'
        );
    }
}